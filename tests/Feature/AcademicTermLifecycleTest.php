<?php

use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Enrollment;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\AcademicTermActivationService;
use App\Support\AcademicTermContext;
use Spatie\Permission\Models\Role;

function lifecycleTerm(string $name): AcademicTerm
{
    return AcademicTerm::query()->create(['display_name' => $name, 'canonical_name' => str($name)->slug().'-'.str()->uuid()]);
}

it('resolves exactly the configured current academic term', function (): void {
    $first = lifecycleTerm('الفصل الأول');
    $second = lifecycleTerm('الفصل الثاني');
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $second->id);

    expect(app(AcademicTermContext::class)->current()?->is($second))->toBeTrue()
        ->and(app(AcademicTermContext::class)->isCurrent($first))->toBeFalse();
});

it('activates a term without changing historical academic data or users', function (): void {
    $old = lifecycleTerm('الفصل القديم');
    $new = lifecycleTerm('الفصل الجديد');
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $old->id);
    $actor = User::factory()->create(['role' => 'super_admin', 'password' => 'secret', 'login_username' => 'admin-old', 'must_change_password' => false]);
    Role::findOrCreate('super-admin', 'web');
    $actor->assignRole('super-admin');
    $student = \App\Models\Student::query()->create(['name' => 'طالب', 'student_number' => '1001']);
    $subject = Subject::query()->create(['code' => 'TERM-1', 'name' => 'مادة', 'subject_type' => Subject::TYPE_THEORETICAL]);
    $section = SubjectSection::query()->create(['academic_term_id' => $old->id, 'subject_id' => $subject->id, 'code' => 'T1']);
    $enrollment = Enrollment::query()->create(['academic_term_id' => $old->id, 'student_id' => $student->id, 'subject_id' => $subject->id]);
    $batch = ImportBatch::query()->create(['deduplication_key' => hash('sha256', 'lifecycle-slot'), 'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE, 'status' => ImportBatch::STATUS_COMPLETED]);
    $hall = Hall::query()->create(['code' => 'LIFE-H1', 'name' => 'قاعة', 'is_active' => true]);
    $slot = SubjectSectionScheduleSlot::query()->create(['import_batch_id' => $batch->id, 'academic_term_id' => $old->id, 'subject_id' => $subject->id, 'subject_section_id' => $section->id, 'weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00']);
    $session = LectureSession::query()->create(['academic_term_id' => $old->id, 'subject_id' => $subject->id, 'subject_section_id' => $section->id, 'lecturer_id' => $actor->id, 'hall_id' => $hall->id, 'session_date' => today(), 'start_time' => '08:00', 'end_time' => '09:00', 'status' => 'scheduled']);
    $password = $actor->getRawOriginal('password');

    app(AcademicTermActivationService::class)->activate($new, $actor);

    expect(app(AcademicTermContext::class)->currentId())->toBe($new->id)
        ->and($old->fresh()->is_archived)->toBeTrue()
        ->and(Enrollment::query()->find($enrollment->id)?->academic_term_id)->toBe($old->id)
        ->and(SubjectSection::query()->find($section->id)?->academic_term_id)->toBe($old->id)
        ->and(SubjectSectionScheduleSlot::query()->find($slot->id)?->academic_term_id)->toBe($old->id)
        ->and(LectureSession::query()->find($session->id)?->academic_term_id)->toBe($old->id)
        ->and($actor->fresh()->getRawOriginal('password'))->toBe($password)
        ->and($actor->fresh()->login_username)->toBe('admin-old')
        ->and($actor->fresh()->must_change_password)->toBeFalse();
});

it('limits explicit current-term scopes to the active term', function (): void {
    $old = lifecycleTerm('فصل قديم');
    $current = lifecycleTerm('فصل حالي');
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $current->id);
    $subject = Subject::query()->create(['code' => 'TERM-2', 'name' => 'مادة', 'subject_type' => Subject::TYPE_THEORETICAL]);
    SubjectSection::query()->create(['academic_term_id' => $old->id, 'subject_id' => $subject->id, 'code' => 'T1']);
    $currentSection = SubjectSection::query()->create(['academic_term_id' => $current->id, 'subject_id' => $subject->id, 'code' => 'T2']);

    expect(SubjectSection::query()->forCurrentAcademicTerm()->pluck('id')->all())->toBe([$currentSection->id]);
});
