<?php

use App\Filament\Resources\LectureSessions\Pages\ListLectureSessions;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-19 10:00:00'));

    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $term = AcademicTerm::query()->create([
        'display_name' => 'Tabs Test Term',
        'canonical_name' => 'tabs-test-term-'.uniqid(),
        'teaching_start_date' => '2026-05-01',
        'teaching_end_date' => '2026-05-31',
    ]);
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $term->id);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function lectureSessionTabsAdmin(): User
{
    $user = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('super-admin');

    return $user;
}

function lectureSessionTabsSubject(User $lecturer): Subject
{
    return Subject::query()->create([
        'code' => 'TABS-101',
        'name' => 'Tabs Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'is_active' => true,
    ]);
}

function lectureSessionTabsHall(): Hall
{
    return Hall::query()->create([
        'code' => 'H-TABS',
        'name' => 'Tabs Hall',
        'floor' => 1,
        'is_active' => true,
    ]);
}

function lectureSessionTabsRecord(Subject $subject, Hall $hall, array $overrides): LectureSession
{
    return LectureSession::query()->create([
        'academic_term_id' => AcademicTerm::query()->sole()->id,
        'subject_id' => $subject->id,
        'lecturer_id' => $subject->lecturer_id,
        'hall_id' => $hall->id,
        'session_date' => today()->toDateString(),
        'start_time' => '11:00:00',
        'end_time' => '12:00:00',
        'status' => 'scheduled',
        'attendance_mode' => 'qr_otp',
        'qr_refresh_rate' => 120,
        ...$overrides,
    ]);
}

it('separates current and ended sessions for today without overlap', function (): void {
    $admin = lectureSessionTabsAdmin();
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $subject = lectureSessionTabsSubject($lecturer);
    $hall = lectureSessionTabsHall();

    $todayUpcoming = lectureSessionTabsRecord($subject, $hall, [
        'session_date' => today()->toDateString(),
        'start_time' => '11:00:00',
        'end_time' => '12:00:00',
        'status' => 'scheduled',
    ]);
    $todayCompleted = lectureSessionTabsRecord($subject, $hall, [
        'session_date' => today()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'completed',
    ]);
    $yesterdayCompleted = lectureSessionTabsRecord($subject, $hall, [
        'session_date' => today()->subDay()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'completed',
    ]);
    $tomorrowUpcoming = lectureSessionTabsRecord($subject, $hall, [
        'session_date' => today()->addDay()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'scheduled',
    ]);

    Livewire::actingAs($admin)
        ->test(ListLectureSessions::class)
        ->assertSet('activeTab', 'today')
        ->assertCanSeeTableRecords([$todayUpcoming])
        ->assertCanNotSeeTableRecords([$todayCompleted, $yesterdayCompleted, $tomorrowUpcoming])
        ->set('activeTab', 'today_ended')
        ->assertCanSeeTableRecords([$todayCompleted])
        ->assertCanNotSeeTableRecords([$todayUpcoming, $yesterdayCompleted, $tomorrowUpcoming])
        ->set('activeTab', 'completed')
        ->assertCanSeeTableRecords([$todayCompleted, $yesterdayCompleted])
        ->assertCanNotSeeTableRecords([$todayUpcoming, $tomorrowUpcoming])
        ->set('activeTab', 'upcoming')
        ->assertCanSeeTableRecords([$todayUpcoming, $tomorrowUpcoming])
        ->assertCanNotSeeTableRecords([$todayCompleted, $yesterdayCompleted]);
});
