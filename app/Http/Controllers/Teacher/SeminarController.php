<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Seminar;
use App\Support\QrUrlGenerator;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeminarController extends Controller
{
    public function index()
    {
        $seminarsQuery = Seminar::withCount('attendances')->latest();

        if (! auth()->user()?->hasRole('super-admin')) {
            $seminarsQuery->where('created_by', auth()->id());
        }

        $seminars = $seminarsQuery->paginate(12);

        return view('teacher.seminars.index', compact('seminars'));
    }

    public function create()
    {
        return view('teacher.seminars.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience_type' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['nullable', 'string', 'max:2000'],
            'collect_specialization' => ['nullable', 'boolean'],
            'collect_profession' => ['nullable', 'boolean'],
            'collect_academic_rank' => ['nullable', 'boolean'],
            'collect_age' => ['nullable', 'boolean'],
            'collect_organization' => ['nullable', 'boolean'],
            'collect_phone' => ['nullable', 'boolean'],
            'collect_email' => ['nullable', 'boolean'],
            'collect_notes' => ['nullable', 'boolean'],
        ]);

        $seminar = Seminar::create($data + [
            'created_by' => auth()->id(),
            'collect_specialization' => $request->boolean('collect_specialization'),
            'collect_profession' => $request->boolean('collect_profession'),
            'collect_academic_rank' => $request->boolean('collect_academic_rank'),
            'collect_age' => $request->boolean('collect_age'),
            'collect_organization' => $request->boolean('collect_organization'),
            'collect_phone' => $request->boolean('collect_phone'),
            'collect_email' => $request->boolean('collect_email'),
            'collect_notes' => $request->boolean('collect_notes'),
        ]);

        $seminar->activateQr();

        return redirect()
            ->route('teacher.seminars.show', $seminar)
            ->with('success', __('teacher.seminar_created'));
    }

    public function show(Seminar $seminar)
    {
        $this->authorizeSeminar($seminar);
        $seminar->syncLifecycleState();
        $seminar->refresh();

        $attendances = $seminar->attendances()
            ->latest('attended_at')
            ->paginate(20);

        return view('teacher.seminars.show', compact('seminar', 'attendances'));
    }

    public function start(Seminar $seminar)
    {
        $this->authorizeSeminar($seminar);
        $seminar->activateQr();

        return redirect()->route('teacher.seminars.qr', $seminar);
    }

    public function openQr(Seminar $seminar)
    {
        $this->authorizeSeminar($seminar);

        if ($seminar->status !== 'active' || $seminar->qr_expired || ! $seminar->qr_token) {
            $seminar->activateQr();
        }

        return redirect()->route('teacher.seminars.qr', $seminar);
    }

    public function qr(Seminar $seminar, QrUrlGenerator $qrUrlGenerator)
    {
        $this->authorizeSeminar($seminar);
        $seminar->syncLifecycleState();
        $seminar->refresh();

        if ($seminar->status !== 'active' || $seminar->qr_expired || ! $seminar->qr_token) {
            return view('teacher.seminars.qr', [
                'seminar' => $seminar,
                'qr' => null,
                'expired' => true,
            ]);
        }

        $tokenData = $qrUrlGenerator->route('seminars.attendance.scan', ['token' => $seminar->qr_token]);

        $writer = new PngWriter;
        $qrCode = new \Endroid\QrCode\QrCode(
            data: $tokenData,
            size: 1100,
            margin: 18,
        );
        $result = $writer->write($qrCode);

        return view('teacher.seminars.qr', [
            'seminar' => $seminar,
            'qr' => $result->getDataUri(),
            'expired' => false,
        ]);
    }

    public function status(Seminar $seminar): JsonResponse
    {
        $this->authorizeSeminar($seminar);
        $seminar->syncLifecycleState();
        $seminar->refresh();

        return response()->json([
            'active' => $seminar->status === 'active' && ! $seminar->qr_expired,
        ]);
    }

    public function expireQr(Seminar $seminar): JsonResponse
    {
        $this->authorizeSeminar($seminar);

        if ($seminar->status === 'active' && ! $seminar->qr_expired) {
            $seminar->update([
                'status' => 'completed',
                'qr_expired' => true,
                'qr_expires_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('teacher.seminar_qr_stopped'),
        ]);
    }

    public function export(Seminar $seminar): StreamedResponse
    {
        $this->authorizeSeminar($seminar);

        $filename = 'seminar-attendance-'.$seminar->id.'.csv';

        return response()->streamDownload(function () use ($seminar) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name',
                'Specialization',
                'Profession',
                'Academic Rank',
                'Age',
                'Organization',
                'Phone',
                'Email',
                'Attended At',
                'Notes',
            ]);

            $seminar->attendances()
                ->oldest('attended_at')
                ->chunk(200, function ($attendances) use ($handle) {
                    foreach ($attendances as $attendance) {
                        fputcsv($handle, [
                            $attendance->full_name,
                            $attendance->specialization,
                            $attendance->profession,
                            $attendance->academic_rank,
                            $attendance->age,
                            $attendance->organization,
                            $attendance->phone,
                            $attendance->email,
                            optional($attendance->attended_at)->format('Y-m-d H:i:s'),
                            $attendance->notes,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeSeminar(Seminar $seminar): void
    {
        abort_unless($seminar->canManage(auth()->user()), 403);
    }
}
