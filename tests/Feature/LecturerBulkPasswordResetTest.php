<?php

use App\Exports\LecturerAccountReportExport;
use App\Filament\Pages\LecturerAccountPreparation;
use App\Models\AcademicTerm;
use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\LecturerAccountGenerationItem;
use App\Models\LecturerAccountGenerationRun;
use App\Models\LecturerCredentialBatch;
use App\Models\LecturerPasswordResetOperation;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use App\Services\LecturerBulkPasswordResetService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('services.lecturer_credentials.key', str_repeat('r', 48));
    config()->set('services.lecturer_credentials.key_version', 'reset-v1');
    foreach (['admin', 'super-admin', 'manager', 'course_lecturer'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    Permission::findOrCreate('reset lecturer passwords', 'web');
    Permission::findOrCreate('download lecturer credential batches', 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function passwordResetActor(string $role = 'admin', bool $permitted = true): User
{
    $user = User::factory()->create(['role' => $role, 'type' => 'admin', 'status' => 'active', 'is_active' => true]);
    $user->assignRole($role);
    if ($permitted) {
        $user->givePermissionTo('reset lecturer passwords');
        $user->givePermissionTo('download lecturer credential batches');
    }

    return $user;
}

function resettableLecturer(string $username, array $overrides = []): array
{
    $user = User::factory()->create([
        'name' => 'ندى محمد محمود',
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
        'login_username' => $username,
        'password' => Hash::make('OldPassword!234'),
        ...$overrides,
    ]);
    $user->assignRole('course_lecturer');
    $lecturer = Lecturer::query()->create(['name' => 'ندى محمد محمود', 'canonical_name' => 'ندى محمد محمود', 'user_id' => $user->id, 'is_active' => true]);

    return compact('user', 'lecturer');
}

it('previews without writes and precisely excludes non lecturer accounts', function (): void {
    $eligible = resettableLecturer('nada187');
    $inactive = resettableLecturer('inactive188', ['is_active' => false]);
    $noRole = resettableLecturer('norole189');
    $noRole['user']->removeRole('course_lecturer');
    $noUsername = resettableLecturer('', ['login_username' => null]);
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_active' => true]);
    $student = User::factory()->create(['role' => 'student', 'type' => 'student', 'status' => 'active', 'is_active' => true]);
    $before = [User::count(), LecturerCredentialBatch::count(), LecturerPasswordResetOperation::count()];

    $preview = app(LecturerBulkPasswordResetService::class)->preview([
        $eligible['user']->id, $inactive['user']->id, $noRole['user']->id, $noUsername['user']->id, $admin->id, $student->id, 'broken', 999999,
    ]);

    expect($preview['eligible_count'])->toBe(1)
        ->and($preview['excluded_count'])->toBe(7)
        ->and(collect($preview['rows'])->pluck('reason')->filter()->all())->toContain('الحساب غير فعال', 'الحساب لا يملك صلاحية مدرس مقرر', 'اسم الدخول غير جاهز', 'لا يوجد ارتباط بهوية محاضر', 'الحساب لم يعد موجودًا')
        ->and([User::count(), LecturerCredentialBatch::count(), LecturerPasswordResetOperation::count()])->toBe($before)
        ->and($preview['proposed_batch_filename'])->toContain('إعادة-ضبط');
});

it('has a deterministic preview fingerprint that is invalidated by account changes', function (): void {
    $fixture = resettableLecturer('nada187');
    $service = app(LecturerBulkPasswordResetService::class);
    $first = $service->preview([$fixture['user']->id]);
    $second = $service->preview([$fixture['user']->id]);
    $fixture['user']->forceFill(['login_username' => 'nada188'])->save();
    $changed = $service->preview([$fixture['user']->id]);

    expect($first['fingerprint'])->toBe($second['fingerprint'])
        ->and($changed['fingerprint'])->not->toBe($first['fingerprint']);
});

it('lists only eligible legacy lecturer accounts in the modal', function (): void {
    $legacy = resettableLecturer('legacylec', ['must_change_password' => true]);
    $recoverable = resettableLecturer('recoverablelec', [
        'must_change_password' => true,
        'temporary_password_encrypted' => Crypt::encryptString('OldPassword!234'),
    ]);
    $changed = resettableLecturer('changedlec', ['must_change_password' => false]);
    $inactive = resettableLecturer('inactivelec', ['must_change_password' => true, 'is_active' => false]);
    $deleted = resettableLecturer('deletedlec', ['must_change_password' => true]);
    $deleted['user']->delete();
    $noRole = resettableLecturer('norolelec', ['must_change_password' => true]);
    $noRole['user']->removeRole('course_lecturer');
    $unlinked = User::factory()->create(['must_change_password' => true, 'temporary_password_encrypted' => null, 'login_username' => 'unlinkedlec', 'status' => 'active', 'is_active' => true]);
    $unlinked->assignRole('course_lecturer');
    Lecturer::query()->create(['name' => 'مدرس نظري بديل', 'canonical_name' => 'مدرس نظري بديل', 'placeholder_type' => Lecturer::PLACEHOLDER_THEORETICAL]);

    $service = app(LecturerBulkPasswordResetService::class);
    expect($service->legacyCandidatesQuery()->pluck('users.id')->all())->toBe([$legacy['user']->id])
        ->and($service->legacyCandidatesQuery()->whereKey($recoverable['user']->id)->exists())->toBeFalse()
        ->and($service->legacyCandidatesQuery()->whereKey($changed['user']->id)->exists())->toBeFalse()
        ->and($service->legacyCandidatesQuery()->whereKey($inactive['user']->id)->exists())->toBeFalse()
        ->and($service->legacyCandidatesQuery()->whereKey($deleted['user']->id)->exists())->toBeFalse();

    $component = Livewire::actingAs(passwordResetActor())->test(LecturerAccountPreparation::class)
        ->assertTableActionVisible('reset-legacy-lecturer-passwords');
    $instance = $component->instance();
    $instance->mountTableAction('reset-legacy-lecturer-passwords');
    $action = $instance->getMountedTableAction();
    $checkboxes = collect($instance->getMountedTableActionForm($action)?->getFlatComponents(withHidden: true) ?? [])
        ->first(fn (object $field): bool => method_exists($field, 'getName') && $field->getName() === 'user_ids');

    expect($checkboxes)->not->toBeNull()
        ->and(array_keys($checkboxes->getOptions()))->toBe([$legacy['user']->id])
        ->and($checkboxes->getExtraAttributes()['class'] ?? null)->toBe('legacy-lecturer-reset-options')
        ->and($checkboxes->getSelectAllAction()->getLabel())
        ->toBe(__('lecturer-account-preparation.actions.select_all_legacy_page'))
        ->and($checkboxes->getDeselectAllAction()->getLabel())
        ->toBe(__('lecturer-account-preparation.actions.deselect_all_legacy_page'));
});

it('shows the selected legacy account count and keeps checkbox selection state', function (): void {
    $first = resettableLecturer('legacyselected1', ['must_change_password' => true]);
    $second = resettableLecturer('legacyselected2', ['must_change_password' => true]);

    $component = Livewire::actingAs(passwordResetActor())->test(LecturerAccountPreparation::class)
        ->mountTableAction('reset-legacy-lecturer-passwords');

    $getFormComponent = function (string $name) use ($component): object {
        $instance = $component->instance();
        $action = $instance->getMountedTableAction();

        return collect($instance->getMountedTableActionForm($action)?->getFlatComponents(withHidden: true) ?? [])
            ->firstOrFail(fn (object $field): bool => method_exists($field, 'getName') && $field->getName() === $name);
    };

    expect($getFormComponent('legacy_selected_count')->getLabel())
        ->toBe(__('lecturer-account-preparation.actions.legacy_selected_accounts'))
        ->and($getFormComponent('legacy_selected_count')->getContent())
        ->toBe(__('lecturer-account-preparation.actions.legacy_selected_none'));

    $component->setTableActionData(['user_ids' => [$first['user']->id]])
        ->assertTableActionDataSet(['user_ids' => [$first['user']->id]]);

    expect($getFormComponent('legacy_selected_count')->getContent())
        ->toBe(__('lecturer-account-preparation.actions.legacy_selected_count', ['count' => 1]));

    $component->setTableActionData(['user_ids' => [$first['user']->id, $second['user']->id]])
        ->assertTableActionDataSet(['user_ids' => [$first['user']->id, $second['user']->id]]);

    expect($getFormComponent('legacy_selected_count')->getContent())
        ->toBe(__('lecturer-account-preparation.actions.legacy_selected_count', ['count' => 2]));

    $component->setTableActionData(['user_ids' => []])
        ->assertTableActionDataSet(['user_ids' => []]);

    expect($getFormComponent('legacy_selected_count')->getContent())
        ->toBe(__('lecturer-account-preparation.actions.legacy_selected_none'));
});

it('pages legacy modal choices on the server with at most 50 accounts', function (): void {
    for ($index = 1; $index <= 51; $index++) {
        resettableLecturer('legacy'.str_pad((string) $index, 3, '0', STR_PAD_LEFT), ['must_change_password' => true]);
    }

    $component = Livewire::actingAs(passwordResetActor())->test(LecturerAccountPreparation::class)
        ->mountTableAction('reset-legacy-lecturer-passwords');
    $instance = $component->instance();
    $action = $instance->getMountedTableAction();
    $checkboxes = collect($instance->getMountedTableActionForm($action)?->getFlatComponents(withHidden: true) ?? [])
        ->first(fn (object $field): bool => method_exists($field, 'getName') && $field->getName() === 'user_ids');
    expect($checkboxes->getOptions())->toHaveCount(50);

    $component->setTableActionData(['legacy_page' => 2]);
    $instance = $component->instance();
    $action = $instance->getMountedTableAction();
    $checkboxes = collect($instance->getMountedTableActionForm($action)?->getFlatComponents(withHidden: true) ?? [])
        ->first(fn (object $field): bool => method_exists($field, 'getName') && $field->getName() === 'user_ids');
    expect($checkboxes->getOptions())->toHaveCount(1);

    $component->setTableActionData(['legacy_search' => 'legacy051', 'legacy_page' => 1]);
    $instance = $component->instance();
    $action = $instance->getMountedTableAction();
    $checkboxes = collect($instance->getMountedTableActionForm($action)?->getFlatComponents(withHidden: true) ?? [])
        ->first(fn (object $field): bool => method_exists($field, 'getName') && $field->getName() === 'user_ids');
    expect($checkboxes->getOptions())->toHaveCount(1);
});

it('resets only selected legacy accounts and leaves lecture session ownership unchanged', function (): void {
    $first = resettableLecturer('legacyfirst', ['must_change_password' => true]);
    $second = resettableLecturer('legacysecond', ['must_change_password' => true]);
    $untouched = resettableLecturer('legacyuntouched', ['must_change_password' => true]);
    $actor = passwordResetActor();
    $term = AcademicTerm::query()->create(['display_name' => 'فصل قديم', 'canonical_name' => 'legacy-term']);
    $subject = Subject::query()->create(['code' => 'LEG101', 'name' => 'مادة قديمة', 'subject_type' => Subject::TYPE_THEORETICAL, 'is_active' => true]);
    $section = SubjectSection::query()->create(['academic_term_id' => $term->id, 'subject_id' => $subject->id, 'section_type' => Subject::TYPE_THEORETICAL, 'code' => 'T1', 'name' => 'T1']);
    $hall = Hall::query()->create(['code' => 'LEG-H1', 'name' => 'قاعة اختبار', 'is_active' => true]);
    $session = LectureSession::query()->create([
        'academic_term_id' => $term->id, 'subject_id' => $subject->id, 'subject_section_id' => $section->id,
        'lecturer_id' => $first['user']->id, 'lecturer_identity_id' => $first['lecturer']->id,
        'hall_id' => $hall->id, 'session_date' => today()->addWeek(), 'start_time' => '09:00', 'end_time' => '10:00',
    ]);
    $sessionBefore = DB::table('lecture_sessions')->where('id', $session->id)->first();
    $oldFirstHash = $first['user']->password;
    $oldSecondHash = $second['user']->password;
    $untouchedHash = $untouched['user']->password;

    $result = app(LecturerBulkPasswordResetService::class)->resetLegacy([$first['user']->id, $second['user']->id], $actor);
    $batch = $result['batch']->fresh();
    $sheet = spreadsheetFromXlsxBytes(app(\App\Services\LecturerCredentialBatchService::class)->decryptedContents($batch))->getSheetByName('بيانات دخول المدرسين');
    $firstPassword = (string) $sheet?->getCell('C2')->getValue();
    $secondPassword = (string) $sheet?->getCell('C3')->getValue();
    $sessionAfter = DB::table('lecture_sessions')->where('id', $session->id)->first();

    expect($result['reset_count'])->toBe(2)
        ->and($batch->batch_type)->toBe('password_reset')
        ->and($batch->status)->toBe('available')
        ->and($batch->recipient_user_ids)->toBe([$first['user']->id, $second['user']->id])
        ->and($batch->record_count)->toBe(2)
        ->and($batch->encrypted_file_path)->toBeNull()
        ->and($batch->generated_by)->toBe($actor->id)
        ->and($first['user']->fresh()->password)->not->toBe($oldFirstHash)
        ->and($second['user']->fresh()->password)->not->toBe($oldSecondHash)
        ->and(Hash::check($firstPassword, $first['user']->fresh()->password))->toBeTrue()
        ->and(Hash::check($secondPassword, $second['user']->fresh()->password))->toBeTrue()
        ->and(Crypt::decryptString($first['user']->fresh()->temporary_password_encrypted))->toBe($firstPassword)
        ->and($first['user']->fresh()->must_change_password)->toBeTrue()
        ->and($first['user']->fresh()->password_reset_at)->not->toBeNull()
        ->and($untouched['user']->fresh()->password)->toBe($untouchedHash)
        ->and($untouched['user']->fresh()->temporary_password_encrypted)->toBeNull()
        ->and($sheet?->getCell('D2')->getValue())->toBe('تمت إعادة تعيين كلمة المرور')
        ->and($sheet?->getCell('E2')->getValue())->toBe('course_lecturer')
        ->and($sheet?->getCell('F2')->getValue())->not->toBeEmpty()
        ->and(\App\Models\LectureSession::query()->count())->toBe(1)
        ->and($sessionAfter->lecturer_id)->toBe($sessionBefore->lecturer_id)
        ->and($sessionAfter->lecturer_identity_id)->toBe($sessionBefore->lecturer_identity_id)
        ->and($sessionAfter->hall_id)->toBe($sessionBefore->hall_id)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(app(LecturerBulkPasswordResetService::class)->legacyCandidatesQuery()->pluck('users.id')->all())->toBe([$untouched['user']->id]);

    expect(fn () => app(LecturerBulkPasswordResetService::class)->resetLegacy([$first['user']->id], $actor))->toThrow(RuntimeException::class);

    $this->actingAs($second['user']->fresh())->put('/password/force-change', [
        'current_password' => $secondPassword,
        'password' => 'NewPersonalPassword!234',
        'password_confirmation' => 'NewPersonalPassword!234',
    ])->assertRedirect('/admin');

    $redownload = spreadsheetFromXlsxBytes(app(\App\Services\LecturerCredentialBatchService::class)->decryptedContents($batch))->getSheetByName('بيانات دخول المدرسين');
    expect($redownload?->getCell('C2')->getValue())->toBe($firstPassword)
        ->and($redownload?->getCell('C3')->getValue())->toBe('—')
        ->and($second['user']->fresh()->temporary_password_encrypted)->toBeNull();
});

it('downloads a report immediately from the legacy reset action and makes its batch selectable', function (): void {
    $legacy = resettableLecturer('legacyui', ['must_change_password' => true]);
    $actor = passwordResetActor();

    $emptyComponent = Livewire::actingAs($actor)->test(LecturerAccountPreparation::class);
    $emptyInstance = $emptyComponent->instance();
    $emptyInstance->mountTableAction('download-current-lecturer-password-report');
    $emptyAction = $emptyInstance->getMountedTableAction();
    $emptyHint = collect($emptyInstance->getMountedTableActionForm($emptyAction)?->getFlatComponents(withHidden: true) ?? [])
        ->first(fn (object $field): bool => method_exists($field, 'getName') && $field->getName() === 'current_report_hint');
    expect($emptyHint->getContent())->toBe(__('lecturer-account-preparation.actions.no_current_reset_batches'));

    Livewire::actingAs($actor)
        ->test(LecturerAccountPreparation::class)
        ->callTableAction('reset-legacy-lecturer-passwords', data: ['user_ids' => [$legacy['user']->id]])
        ->assertFileDownloaded();

    expect(LecturerCredentialBatch::query()->where('batch_type', 'password_reset')->whereNotNull('recipient_user_ids')->count())->toBe(1)
        ->and($legacy['user']->fresh()->temporary_password_encrypted)->not->toBeNull();

    $component = Livewire::actingAs($actor)->test(LecturerAccountPreparation::class);
    $instance = $component->instance();
    $instance->mountTableAction('download-current-lecturer-password-report');
    $action = $instance->getMountedTableAction();
    $batchField = collect($instance->getMountedTableActionForm($action)?->getFlatComponents(withHidden: true) ?? [])
        ->first(fn (object $field): bool => method_exists($field, 'getName') && $field->getName() === 'batch_id');
    expect($batchField->getOptions())->toHaveKey(LecturerCredentialBatch::query()->latest('id')->value('id'));
});

it('resets only eligible lecturers into one current-state password reset batch', function (): void {
    $first = resettableLecturer('nada187');
    $second = resettableLecturer('sara188');
    $excluded = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_active' => true]);
    $actor = passwordResetActor();
    $oldFirst = $first['user']->password;
    $oldSecond = $second['user']->password;
    $term = AcademicTerm::query()->create(['display_name' => 'الفصل الصيفي 2025/2026', 'canonical_name' => 'summer']);
    $service = app(LecturerBulkPasswordResetService::class);
    $preview = $service->preview([$first['user']->id, $second['user']->id, $excluded->id], $term);
    $result = $service->execute($preview, $preview['fingerprint'], $actor, $term);
    $batch = $result['batch']->fresh();

    expect($result['reset_count'])->toBe(2)
        ->and($batch->batch_type)->toBe('password_reset')
        ->and($batch->record_count)->toBe(2)
        ->and($batch->original_filename)->toContain('إعادة-ضبط')
        ->and($batch->encrypted_file_path)->toBeNull()
        ->and($batch->recipient_user_ids)->toBe([$first['user']->id, $second['user']->id])
        ->and(app(\App\Services\LecturerCredentialBatchService::class)->decryptedContents($batch))->toStartWith('PK')
        ->and($first['user']->fresh()->password)->not->toBe($oldFirst)
        ->and($second['user']->fresh()->password)->not->toBe($oldSecond)
        ->and($first['user']->fresh()->must_change_password)->toBeTrue()
        ->and($second['user']->fresh()->must_change_password)->toBeTrue()
        ->and($excluded->fresh()->password)->toBe($excluded->password)
        ->and($batch->actions()->whereIn('action', ['reset_batch_created', 'generated'])->count())->toBe(2)
        ->and(LecturerPasswordResetOperation::query()->where('fingerprint', $preview['fingerprint'])->exists())->toBeTrue()
        ->and(Storage::disk('local')->allFiles('lecturer-credentials'))->toBe([]);
});

it('shows the latest admin-reset password on an existing-account report without exposing the previous one', function (): void {
    $fixture = resettableLecturer('nada187', ['must_change_password' => true]);
    $actor = passwordResetActor();
    $term = AcademicTerm::query()->create(['display_name' => 'فصل الاختبار', 'canonical_name' => 'term-test']);
    $run = LecturerAccountGenerationRun::query()->create([
        'academic_term_id' => $term->id,
        'status' => LecturerAccountGenerationRun::STATUS_COMPLETED,
        'lecturer_count' => 1,
        'completed_at' => now(),
    ]);
    LecturerAccountGenerationItem::query()->create([
        'run_id' => $run->id,
        'lecturer_id' => $fixture['lecturer']->id,
        'user_id' => $fixture['user']->id,
        'login_username' => $fixture['user']->login_username,
        'result' => LecturerAccountGenerationItem::RESULT_EXISTING_ACCOUNT,
    ]);
    $originalHash = $fixture['user']->password;
    $service = app(LecturerBulkPasswordResetService::class);
    $preview = $service->preview([$fixture['user']->id], $term);
    $result = $service->execute($preview, $preview['fingerprint'], $actor, $term);
    $resetSheet = spreadsheetFromXlsxBytes(app(\App\Services\LecturerCredentialBatchService::class)->decryptedContents($result['batch']))
        ->getSheetByName('بيانات دخول المدرسين');
    $password = (string) $resetSheet?->getCell('C2')->getValue();
    $report = spreadsheetFromXlsxBytes(Excel::raw(LecturerAccountReportExport::successForRun($run), ExcelWriter::XLSX))
        ->getSheetByName('العمليات الناجحة');

    expect($fixture['user']->fresh()->password)->not->toBe($originalHash)
        ->and(Hash::check($password, $fixture['user']->fresh()->password))->toBeTrue()
        ->and($fixture['user']->fresh()->must_change_password)->toBeTrue()
        ->and($fixture['user']->fresh()->password_reset_at)->not->toBeNull()
        ->and(Crypt::decryptString($fixture['user']->fresh()->temporary_password_encrypted))->toBe($password)
        ->and($report?->getCell('C2')->getValue())->toBe($password)
        ->and($fixture['user']->fresh()->toArray())->not->toHaveKey('temporary_password_encrypted');

    $this->travel(1)->seconds();
    $fixture['user']->forceFill(['login_username' => 'nada188'])->save();
    $secondPreview = $service->preview([$fixture['user']->id], $term);
    $second = $service->execute($secondPreview, $secondPreview['fingerprint'], $actor, $term);
    $secondSheet = spreadsheetFromXlsxBytes(app(\App\Services\LecturerCredentialBatchService::class)->decryptedContents($second['batch']))
        ->getSheetByName('بيانات دخول المدرسين');
    $newPassword = (string) $secondSheet?->getCell('C2')->getValue();
    $updatedReport = spreadsheetFromXlsxBytes(Excel::raw(LecturerAccountReportExport::successForRun($run), ExcelWriter::XLSX))
        ->getSheetByName('العمليات الناجحة');

    expect($newPassword)->not->toBe($password)
        ->and($updatedReport?->getCell('C2')->getValue())->toBe($newPassword)
        ->and(Hash::check($password, $fixture['user']->fresh()->password))->toBeFalse();
});

it('regenerates a mixed batch from current account state and never retains the old password after another reset', function (): void {
    $a = resettableLecturer('lecturera');
    $b = resettableLecturer('lecturerb');
    $c = resettableLecturer('lecturerc');
    $actor = passwordResetActor();
    $service = app(LecturerBulkPasswordResetService::class);
    $batches = app(\App\Services\LecturerCredentialBatchService::class);
    $preview = $service->preview([$a['user']->id, $b['user']->id, $c['user']->id]);
    $batch = $service->execute($preview, $preview['fingerprint'], $actor)['batch'];

    $initial = spreadsheetFromXlsxBytes($batches->decryptedContents($batch))->getSheetByName('بيانات دخول المدرسين');
    $passwordA = (string) $initial?->getCell('C2')->getValue();
    $oldPasswordB = (string) $initial?->getCell('C3')->getValue();
    $passwordC = (string) $initial?->getCell('C4')->getValue();
    expect($initial?->getCell('B3')->getValue())->toBe('lecturerb')
        ->and(Hash::check($oldPasswordB, $b['user']->fresh()->password))->toBeTrue();

    $this->actingAs($b['user']->fresh())->put('/password/force-change', [
        'current_password' => $oldPasswordB,
        'password' => 'NewPersonalPassword!234',
        'password_confirmation' => 'NewPersonalPassword!234',
    ])->assertRedirect('/admin');

    $mixed = spreadsheetFromXlsxBytes($batches->decryptedContents($batch))->getSheetByName('بيانات دخول المدرسين');
    expect($mixed?->getCell('C2')->getValue())->toBe($passwordA)
        ->and($mixed?->getCell('C3')->getValue())->toBe('—')
        ->and($mixed?->getCell('C4')->getValue())->toBe($passwordC)
        ->and($b['user']->fresh()->temporary_password_encrypted)->toBeNull()
        ->and($batch->fresh()->encrypted_file_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    $this->travel(1)->seconds();
    $newPreview = $service->preview([$b['user']->id]);
    $service->execute($newPreview, $newPreview['fingerprint'], $actor);
    $afterReset = spreadsheetFromXlsxBytes($batches->decryptedContents($batch))->getSheetByName('بيانات دخول المدرسين');
    $newPasswordB = (string) $afterReset?->getCell('C3')->getValue();

    expect($newPasswordB)->not->toBe($oldPasswordB)
        ->and(Hash::check($newPasswordB, $b['user']->fresh()->password))->toBeTrue()
        ->and($b['user']->fresh()->must_change_password)->toBeTrue()
        ->and($b['user']->fresh()->password_reset_at)->not->toBeNull()
        ->and(json_encode($batch->fresh()->toArray()))->not->toContain($oldPasswordB, $newPasswordB)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects unauthorized, stale, and reused reset previews without a second batch', function (): void {
    $fixture = resettableLecturer('nada187');
    $service = app(LecturerBulkPasswordResetService::class);
    $preview = $service->preview([$fixture['user']->id]);
    $original = $fixture['user']->password;

    expect(fn () => $service->execute($preview, $preview['fingerprint'], passwordResetActor('manager', false)))->toThrow(RuntimeException::class);
    expect($fixture['user']->fresh()->password)->toBe($original);

    $fixture['user']->forceFill(['login_username' => 'changed188'])->save();
    expect(fn () => $service->execute($preview, $preview['fingerprint'], passwordResetActor()))->toThrow(RuntimeException::class);
    expect(LecturerCredentialBatch::count())->toBe(0);

    $fresh = $service->preview([$fixture['user']->id]);
    $actor = passwordResetActor();
    $service->execute($fresh, $fresh['fingerprint'], $actor);
    expect(fn () => $service->execute($fresh, $fresh['fingerprint'], $actor))->toThrow(RuntimeException::class)
        ->and(LecturerCredentialBatch::count())->toBe(1);
});

it('rolls back when temporary password encryption fails', function (): void {
    $fixture = resettableLecturer('nada187');
    $actor = passwordResetActor();
    $service = app(LecturerBulkPasswordResetService::class);
    $preview = $service->preview([$fixture['user']->id]);
    $oldHash = $fixture['user']->password;
    Crypt::shouldReceive('encryptString')->once()->andThrow(new RuntimeException('encryption failed'));

    expect(fn () => $service->execute($preview, $preview['fingerprint'], $actor))->toThrow(RuntimeException::class)
        ->and($fixture['user']->fresh()->password)->toBe($oldHash)
        ->and(LecturerCredentialBatch::count())->toBe(0)
        ->and(LecturerPasswordResetOperation::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
