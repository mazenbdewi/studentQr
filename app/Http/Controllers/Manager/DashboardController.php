<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('manager.dashboard', [
            'todayAttendance' => \App\Models\Attendance::whereDate('created_at', now())->count(),
            'totalStudents' => \App\Models\User::where('type', 'student')->count(),

        ]);
    }

}
