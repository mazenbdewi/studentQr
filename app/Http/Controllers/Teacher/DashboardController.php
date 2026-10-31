<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\LectureSession;
use App\Models\User;
use App\Support\AcademicTermContext;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('teacher.dashboard', [
            'sessionsCount' => LectureSession::query()
                ->where('academic_term_id', app(AcademicTermContext::class)->currentId() ?? 0)
                ->where('lecturer_id', $user->id)
                ->count(),
            'todayAttendance' => Attendance::query()
                ->whereDate('created_at', today())
                ->whereHas('lectureSession', fn (Builder $query): Builder => $query
                    ->where('academic_term_id', app(AcademicTermContext::class)->currentId() ?? 0)
                    ->where('lecturer_id', $user->id))
                ->count(),
            'totalStudents' => User::query()->where('type', 'student')->count(),
            'activeSession' => LectureSession::query()
                ->where('academic_term_id', app(AcademicTermContext::class)->currentId() ?? 0)
                ->where('lecturer_id', $user->id)
                ->where('status', 'active')
                ->first(),
        ]);
    }
}
