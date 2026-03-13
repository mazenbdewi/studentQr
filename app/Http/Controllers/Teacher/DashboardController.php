<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
   public function index()
    {
        $user = auth()->user();

        return view('teacher.dashboard', [
            'sessionsCount' => \App\Models\LectureSession::where('lecturer_id', $user->id)->count(),
            'todayAttendance' => \App\Models\Attendance::whereDate('created_at', now())->count(),
            'totalStudents' =>  \App\Models\User::where('type',  'student')->count(),
            'activeSession' => \App\Models\LectureSession::where('lecturer_id', $user->id)
                ->where('status', 'active')
                ->first(),
        ]);
    }

}
