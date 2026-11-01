<?php

use App\Models\AcademicTerm;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\LecturerAccountPreparationService;
use App\Services\SchedulePlaceholderService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

it('resolves one idempotent theoretical and practical placeholder lecturer', function (): void {
    $service = app(SchedulePlaceholderService::class);

    $theoretical = $service->theoreticalLecturer();
    $sameTheoretical = app(SchedulePlaceholderService::class)->theoreticalLecturer();
    $practical = $service->practicalLecturer();
    $samePractical = app(SchedulePlaceholderService::class)->practicalLecturer();

    expect($theoretical->id)->toBe($sameTheoretical->id)
        ->and($theoretical->name)->toBe('مدرس نظري غير محدد')
        ->and($theoretical->name_en)->toBe('Unassigned Theoretical Lecturer')
        ->and($theoretical->isPlaceholder())->toBeTrue()
        ->and($theoretical->isTheoreticalPlaceholder())->toBeTrue()
        ->and($theoretical->isPracticalPlaceholder())->toBeFalse()
        ->and($practical->id)->toBe($samePractical->id)
        ->and($practical->name)->toBe('مدرس عملي غير محدد')
        ->and($practical->name_en)->toBe('Unassigned Practical Lecturer')
        ->and($practical->isPlaceholder())->toBeTrue()
        ->and($practical->isTheoreticalPlaceholder())->toBeFalse()
        ->and($practical->isPracticalPlaceholder())->toBeTrue()
        ->and(Lecturer::query()->whereNotNull('placeholder_type')->count())->toBe(2);
});

it('resolves one idempotent X0 placeholder hall', function (): void {
    $service = app(SchedulePlaceholderService::class);

    $hall = $service->unassignedHall();
    $sameHall = app(SchedulePlaceholderService::class)->unassignedHall();

    expect($hall->id)->toBe($sameHall->id)
        ->and($hall->code)->toBe('X0')
        ->and($hall->name)->toBe('قاعة غير محددة')
        ->and($hall->name_en)->toBe('Unassigned Hall')
        ->and($hall->isPlaceholder())->toBeTrue()
        ->and(Hall::query()->whereNotNull('placeholder_type')->count())->toBe(1);
});

it('does not classify real lecturers or halls as placeholders', function (): void {
    $lecturer = Lecturer::query()->create([
        'name' => 'مدرس حقيقي',
        'canonical_name' => 'مدرس حقيقي',
        'is_active' => true,
    ]);
    $hall = Hall::query()->create([
        'code' => 'A1',
        'name' => 'قاعة حقيقية',
        'is_active' => true,
    ]);

    expect($lecturer->isPlaceholder())->toBeFalse()
        ->and($lecturer->isTheoreticalPlaceholder())->toBeFalse()
        ->and($lecturer->isPracticalPlaceholder())->toBeFalse()
        ->and($hall->isPlaceholder())->toBeFalse();
});

it('enforces unique placeholder identities in the database', function (): void {
    $service = app(SchedulePlaceholderService::class);
    $service->theoreticalLecturer();
    $service->unassignedHall();

    expect(fn () => Lecturer::query()->create([
        'name' => 'Duplicate theoretical placeholder',
        'placeholder_type' => Lecturer::PLACEHOLDER_THEORETICAL,
        'is_active' => true,
    ]))->toThrow(QueryException::class)
        ->and(fn () => Hall::query()->create([
            'code' => 'X1',
            'name' => 'Duplicate unassigned hall',
            'placeholder_type' => Hall::PLACEHOLDER_UNASSIGNED,
            'is_active' => true,
        ]))->toThrow(QueryException::class);
});

it('excludes placeholder lecturers from account preparation and refuses direct account creation', function (): void {
    $placeholder = app(SchedulePlaceholderService::class)->theoreticalLecturer();
    $term = AcademicTerm::query()->create([
        'display_name' => 'Placeholder foundation term',
        'canonical_name' => 'placeholder-foundation-term',
    ]);
    $subject = Subject::query()->create([
        'code' => 'PLACEHOLDER101',
        'name' => 'Placeholder foundation subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'is_active' => true,
    ]);
    $section = SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T1',
        'name' => 'T1',
    ]);
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'placeholder-foundation-schedule'),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'status' => ImportBatch::STATUS_COMPLETED,
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);
    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $placeholder->id,
        'hall_id' => app(SchedulePlaceholderService::class)->unassignedHall()->id,
        'weekday' => 1,
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
    ]);

    $accounts = app(LecturerAccountPreparationService::class);
    $usersBefore = User::query()->count();
    $preview = $accounts->previewBulkPreparation($term);
    $result = $accounts->prepareBulkAccounts($term);

    expect($preview['referenced_lecturer_count'])->toBe(0)
        ->and($preview['accounts_to_create_count'])->toBe(0)
        ->and($result['created_account_count'])->toBe(0)
        ->and($result['credential_rows'])->toBe([])
        ->and(User::query()->count())->toBe($usersBefore)
        ->and($placeholder->fresh()->user_id)->toBeNull()
        ->and(fn () => $accounts->createLoginAccount(
            $placeholder,
            'placeholder@example.test',
            'TemporaryPassword123!',
            'TemporaryPassword123!',
        ))->toThrow(ValidationException::class)
        ->and(User::query()->count())->toBe($usersBefore);
});

it('refuses linking any user account to a placeholder lecturer', function (): void {
    $placeholder = app(SchedulePlaceholderService::class)->practicalLecturer();
    $user = User::factory()->create();
    $accounts = app(LecturerAccountPreparationService::class);

    expect(fn () => $accounts->linkExistingAccount($placeholder, $user))
        ->toThrow(ValidationException::class)
        ->and(fn () => $placeholder->forceFill(['user_id' => $user->id])->save())
        ->toThrow(InvalidArgumentException::class)
        ->and($placeholder->fresh()->user_id)->toBeNull();
});
