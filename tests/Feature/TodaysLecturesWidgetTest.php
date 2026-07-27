<?php

use App\Filament\Widgets\TodaysLecturesWidget;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('shows only today lecture sessions for the current account owner', function (): void {
    $term = AcademicTerm::query()->create([
        'display_name' => 'فصل ودجت اليوم',
        'canonical_name' => 'todays-lectures-'.str()->uuid(),
    ]);
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $term->id);
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
    ]);

    $otherLecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
    ]);

    $hall = Hall::query()->create([
        'code' => 'H-TODAY',
        'name' => 'Today Hall',
        'floor' => 1,
        'is_active' => true,
    ]);

    $subject = Subject::query()->create([
        'code' => 'TODAY-101',
        'name' => 'Today Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'is_active' => true,
    ]);

    $otherSubject = Subject::query()->create([
        'code' => 'TODAY-102',
        'name' => 'Other Today Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $otherLecturer->id,
        'is_active' => true,
    ]);

    $ownTodaySession = LectureSession::query()->create([
        'subject_id' => $subject->id,
        'academic_term_id' => $term->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'session_date' => today(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'status' => 'scheduled',
    ]);

    $otherTodaySession = LectureSession::query()->create([
        'subject_id' => $otherSubject->id,
        'academic_term_id' => $term->id,
        'lecturer_id' => $otherLecturer->id,
        'hall_id' => $hall->id,
        'session_date' => today(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'status' => 'scheduled',
    ]);

    $ownTomorrowSession = LectureSession::query()->create([
        'subject_id' => $subject->id,
        'academic_term_id' => $term->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'session_date' => today()->addDay(),
        'start_time' => '11:00:00',
        'end_time' => '12:00:00',
        'status' => 'scheduled',
    ]);

    expect(TodaysLecturesWidget::getTodaysLecturesQueryForUser($lecturer->id)->pluck('id')->all())
        ->toBe([$ownTodaySession->id])
        ->not->toContain($otherTodaySession->id)
        ->not->toContain($ownTomorrowSession->id);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($lecturer)
        ->test(TodaysLecturesWidget::class)
        ->assertSee('Today Subject')
        ->assertDontSee('Other Today Subject');
});
