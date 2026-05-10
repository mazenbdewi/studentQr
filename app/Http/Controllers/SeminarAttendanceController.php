<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use App\Models\SeminarAttendance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeminarAttendanceController extends Controller
{
    public function scan(string $token)
    {
        $seminar = $this->activeSeminar($token);

        return view('seminars.attendance', [
            'seminar' => $seminar,
            'submitted' => false,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $seminar = $this->activeSeminar($token);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'specialization' => [Rule::requiredIf($seminar->collect_specialization), 'nullable', 'string', 'max:255'],
            'profession' => [Rule::requiredIf($seminar->collect_profession), 'nullable', 'string', 'max:255'],
            'academic_rank' => [Rule::requiredIf($seminar->collect_academic_rank), 'nullable', 'string', 'max:255'],
            'age' => [Rule::requiredIf($seminar->collect_age), 'nullable', 'integer', 'min:1', 'max:120'],
            'organization' => [Rule::requiredIf($seminar->collect_organization), 'nullable', 'string', 'max:255'],
            'phone' => [Rule::requiredIf($seminar->collect_phone), 'nullable', 'string', 'max:40'],
            'email' => [Rule::requiredIf($seminar->collect_email), 'nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $existingAttendance = $this->findExistingAttendance($seminar, $data);

        if ($existingAttendance) {
            return view('seminars.attendance', [
                'seminar' => $seminar,
                'submitted' => true,
                'alreadyRegistered' => true,
            ]);
        }

        SeminarAttendance::create($data + [
            'specialization' => $data['specialization'] ?? null,
            'profession' => $data['profession'] ?? null,
            'academic_rank' => $data['academic_rank'] ?? null,
            'age' => $data['age'] ?? null,
            'organization' => $data['organization'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'seminar_id' => $seminar->id,
            'attended_at' => now(),
            'ip_address' => $request->ip(),
            'device_fingerprint' => $request->userAgent(),
        ]);

        return view('seminars.attendance', [
            'seminar' => $seminar,
            'submitted' => true,
            'alreadyRegistered' => false,
        ]);
    }

    private function findExistingAttendance(Seminar $seminar, array $data): ?SeminarAttendance
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        return SeminarAttendance::query()
            ->where('seminar_id', $seminar->id)
            ->where(function ($query) use ($fullName, $phone, $email) {
                $query->where('full_name', $fullName);

                if ($phone !== '') {
                    $query->orWhere('phone', $phone);
                }

                if ($email !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                }
            })
            ->first();
    }

    private function activeSeminar(string $token): Seminar
    {
        $seminar = Seminar::where('qr_token', $token)->firstOrFail();

        $seminar->syncLifecycleState();
        $seminar->refresh();

        abort_if(
            $seminar->status !== 'active'
            || $seminar->qr_expired
            || ($seminar->qr_expires_at && $seminar->qr_expires_at->isPast()),
            403,
            __('teacher.seminar_qr_expired')
        );

        return $seminar;
    }
}
