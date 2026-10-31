<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Support\AcademicTermContext;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('manager.dashboard', [
            'todayAttendance' => Attendance::query()
                ->whereDate('created_at', today())
                ->whereHas('lectureSession', fn (Builder $query): Builder => $query
                    ->where('academic_term_id', app(AcademicTermContext::class)->currentId() ?? 0))
                ->count(),
            'totalStudents' => User::query()->where('type', 'student')->count(),

        ]);
    }
}
