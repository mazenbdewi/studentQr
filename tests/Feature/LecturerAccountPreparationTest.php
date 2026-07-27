<?php

use App\Exports\LecturerAccountReportExport;
use App\Exports\LecturerLoginCredentialsExport;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\LecturerAccountPreparation;
use App\Models\AcademicTerm;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\LecturerAccountGenerationItem;
use App\Models\LecturerAccountGenerationRun;
use App\Models\LecturerCredentialBatch;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\LecturerAccountPreparationService;
use App\Services\PinLoginService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

function lecturerAccountPreparationAdmin(): User
{
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'login_username' => 'lecturer-preparation-admin',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('super-admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return $admin;
}

function arabicLecturerIdentity(array $overrides = []): Lecturer
{
    return Lecturer::query()->create([
        'name' => 'د. أحمد الخطيب',
        'canonical_name' => 'د. أحمد الخطيب',
        'is_active' => true,
        ...$overrides,
    ]);
}

function lecturerBulkPreparationFixture(): array
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'الفصل الصيفي 2025/2026',
    ]);
    $lecturer = arabicLecturerIdentity(['id' => 211, 'name' => 'محمد ابراهيم علي', 'canonical_name' => 'محمد ابراهيم علي']);
    $subject = Subject::query()->create([
        'code' => 'AEFC716',
        'name' => 'تجهيزات مباني 3',
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
    $hall = Hall::query()->create([
        'code' => 'F-05',
        'name' => 'F-05',
        'floor' => 1,
        'is_active' => true,
    ]);
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'bulk-account-schedule'),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 1,
        'total_rows' => 1,
        'completed_at' => now(),
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);
    $slot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'weekday' => 2,
        'start_time' => '08:30:00',
        'end_time' => '12:30:00',
    ]);

    return compact('term', 'lecturer', 'subject', 'section', 'hall', 'batch', 'slot');
}

function addBulkLecturerSlot(array $fixture, int $lecturerId, string $name): Lecturer
{
    $lecturer = arabicLecturerIdentity(['id' => $lecturerId, 'name' => $name, 'canonical_name' => $name]);
    $section = SubjectSection::query()->create([
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T'.$lecturerId,
        'name' => 'T'.$lecturerId,
    ]);

    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $fixture['batch']->id,
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $fixture['hall']->id,
        'weekday' => 3,
        'start_time' => '10:30:00',
        'end_time' => '12:30:00',
    ]);

    return $lecturer;
}

it('previews bulk lecturer accounts using deterministic usernames without creating users', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    $usersBefore = User::query()->count();

    $preview = app(LecturerAccountPreparationService::class)->previewBulkPreparation($fixture['term']);

    expect($preview['referenced_lecturer_count'])->toBe(1)
        ->and($preview['accounts_to_create_count'])->toBe(1)
        ->and($preview['rows'][0]['lecturer_name'])->toBe('محمد ابراهيم علي')
        ->and($preview['rows'][0]['login_username'])->toBeNull()
        ->and(User::query()->count())->toBe($usersBefore);
});

it('uses login usernames without an email schema dependency', function (): void {
    $user = User::factory()->create([
        'login_username' => 'lec000999',
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    expect($user->fresh()->login_username)->toBe('lec000999');
});

it('bulk creates linked course lecturer accounts with Arabic names and nullable email', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    $sessionsBefore = LectureSession::query()->count();

    $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts(
        $fixture['term']
    );

    $user = $fixture['lecturer']->fresh()->user;
    $temporaryPassword = $result['credential_rows'][0]['temporary_password'];

    expect($result['created_account_count'])->toBe(1)
        ->and($result['credential_rows'])->toHaveCount(1)
        ->and($user->name)->toBe('محمد ابراهيم علي')
        ->and($user->login_username)->toMatch('/^[a-z]+'.$user->id.'$/')
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->status)->toBe('active')
        ->and($user->is_active)->toBeTrue()
        ->and($user->password)->not->toBe($temporaryPassword)
        ->and(Hash::check($temporaryPassword, $user->password))->toBeTrue()
        ->and($user->hasRole('course_lecturer'))->toBeTrue()
        ->and($fixture['lecturer']->fresh()->user_id)->toBe($user->id)
        ->and(app(PinLoginService::class)->findUserForLogin($user->login_username)->id)->toBe($user->id)
        ->and(LectureSession::query()->count())->toBe($sessionsBefore)
        ->and(LecturerAccountGenerationRun::query()->first()->created_count)->toBe(1)
        ->and(LecturerAccountGenerationItem::query()->first()->result)->toBe(LecturerAccountGenerationItem::RESULT_ACCOUNT_CREATED)
        ->and(DB::table('lecturer_account_generation_items')->where('message', 'like', '%'.$temporaryPassword.'%')->exists())->toBeFalse()
        ->and(DB::table('lecturer_account_generation_runs')->where('summary', 'like', '%'.$temporaryPassword.'%')->exists())->toBeFalse();
});

it('persists a stable approved login username for a manually created lecturer account', function (): void {
    lecturerAccountPreparationAdmin();
    $fixture = lecturerBulkPreparationFixture();

    $user = app(LecturerAccountPreparationService::class)->createLoginAccount(
        $fixture['lecturer'],
        'manual-lecturer@example.test',
        'TemporaryPassword123!',
        'TemporaryPassword123!',
    );

    expect($user->fresh()->login_username)->toMatch('/^[a-z]+'.$user->id.'$/')
        ->and($fixture['lecturer']->fresh()->user_id)->toBe($user->id)
        ->and(app(PinLoginService::class)->findUserForLogin($user->fresh()->login_username)?->id)->toBe($user->id);
});

it('assigns a missing username to a linked lecturer account without changing its password', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    $user = User::factory()->create([
        'login_username' => null,
        'password' => Hash::make('keep-linked-password'),
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']));
    $fixture['lecturer']->forceFill(['user_id' => $user->id])->save();
    $passwordHash = $user->password;
    $credentialBatchCount = LecturerCredentialBatch::query()->count();

    $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);

    expect($result['created_account_count'])->toBe(0)
        ->and($result['credential_rows'])->toBe([])
        ->and($user->fresh()->login_username)->toMatch('/^[a-z]+'.$user->id.'$/')
        ->and($user->fresh()->password)->toBe($passwordHash)
        ->and(LecturerCredentialBatch::query()->count())->toBe($credentialBatchCount)
        ->and(LecturerAccountGenerationItem::query()->where('result', LecturerAccountGenerationItem::RESULT_USERNAME_ASSIGNED)->value('login_username'))
        ->toBe($user->fresh()->login_username)
        ->and(LecturerAccountGenerationItem::query()->where('result', LecturerAccountGenerationItem::RESULT_TEMPORARY_PASSWORD_RESET)->exists())
        ->toBeFalse();
});

it('includes an assigned username in credentials exported after resetting a linked lecturer password', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    $user = User::factory()->create([
        'login_username' => null,
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']));
    $fixture['lecturer']->forceFill(['user_id' => $user->id])->save();

    $result = app(LecturerAccountPreparationService::class)->resetTemporaryPasswords(
        $fixture['term'],
        collect([$fixture['lecturer']->fresh()]),
    );
    $spreadsheet = spreadsheetFromXlsxBytes(Excel::raw(
        new LecturerLoginCredentialsExport($result['credential_rows']),
        ExcelWriter::XLSX,
    ));

    expect($user->fresh()->login_username)->toMatch('/^[a-z]+'.$user->id.'$/')
        ->and($result['credential_rows'][0]['login_username'])->toBe($user->fresh()->login_username)
        ->and($spreadsheet->getSheetByName('بيانات دخول المدرسين')?->getCell('C2')->getValue())
        ->toBe($user->fresh()->login_username);
});

it('exports one-time lecturer credentials as private Arabic RTL xlsx for newly created accounts only', function (): void {
    Storage::fake('public');
    $fixture = lecturerBulkPreparationFixture();
    addBulkLecturerSlot($fixture, 213, 'مدرس جديد ثان');
    $existingLecturer = addBulkLecturerSlot($fixture, 212, 'مدرس موجود');
    $existingUser = User::factory()->create([
        'name' => 'مدرس موجود',
        'login_username' => 'lec000212',
        'password' => Hash::make('existing-secret-password'),
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $existingUser->assignRole(Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']));
    $existingLecturer->forceFill(['user_id' => $existingUser->id])->save();

    $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);
    $xlsx = Excel::raw(new LecturerLoginCredentialsExport($result['credential_rows']), ExcelWriter::XLSX);
    $spreadsheet = spreadsheetFromXlsxBytes($xlsx);
    $credentialsSheet = $spreadsheet->getSheetByName('بيانات دخول المدرسين');
    $instructionsSheet = $spreadsheet->getSheetByName('تعليمات الاستخدام');
    $values = spreadsheetCellValues($spreadsheet);
    exec('git status --short -- "*.xlsx"', $gitStatus);

    $response = Excel::download(
        new LecturerLoginCredentialsExport($result['credential_rows']),
        'lecturer-login-credentials-test.xlsx',
        ExcelWriter::XLSX,
        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    );

    $exportedRows = collect($result['credential_rows'])->values();
    $exportedUsernames = $exportedRows->pluck('login_username');

    expect($result['credential_rows'])->toHaveCount(2)
        ->and($response->headers->get('content-type'))->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and((string) $response->headers->get('content-disposition'))->toContain('.xlsx')
        ->and($credentialsSheet)->not->toBeNull()
        ->and($instructionsSheet)->not->toBeNull()
        ->and($credentialsSheet->getRightToLeft())->toBeTrue()
        ->and($instructionsSheet->getRightToLeft())->toBeTrue()
        ->and($credentialsSheet->getCell('A1')->getValue())->toBe('الرقم')
        ->and($credentialsSheet->getCell('B1')->getValue())->toBe('اسم المدرس')
        ->and($credentialsSheet->getCell('C1')->getValue())->toBe('اسم الدخول')
        ->and($credentialsSheet->getCell('D1')->getValue())->toBe('كلمة المرور المؤقتة')
        ->and($credentialsSheet->getCell('G1')->getValue())->toBe('تغيير كلمة المرور عند أول دخول')
        ->and($credentialsSheet->getCell('B2')->getValue())->toBe('محمد ابراهيم علي')
        ->and($credentialsSheet->getCell('C2')->getValue())->toBe($exportedRows[0]['login_username'])
        ->and($credentialsSheet->getCell('D2')->getValue())->toBe($exportedRows[0]['temporary_password'])
        ->and($credentialsSheet->getCell('G2')->getValue())->toBe('نعم')
        ->and($credentialsSheet->getCell('C3')->getValue())->toBe($exportedRows[1]['login_username'])
        ->and($credentialsSheet->getCell('D3')->getValue())->toBe($exportedRows[1]['temporary_password'])
        ->and($credentialsSheet->getCell('G3')->getValue())->toBe('نعم')
        ->and($exportedUsernames)->not->toContain('')
        ->and($exportedUsernames->unique())->toHaveCount(2)
        ->and($exportedRows->every(fn (array $row): bool => User::query()->where('login_username', $row['login_username'])->value('login_username') === $row['login_username']))->toBeTrue()
        ->and($exportedRows->every(fn (array $row): bool => Hash::check($row['temporary_password'], (string) User::query()->where('login_username', $row['login_username'])->value('password'))))->toBeTrue()
        ->and($values)->toContain('يجب تغيير كلمة المرور المؤقتة عند أول تسجيل دخول.')
        ->and($values)->not->toContain('مدرس موجود')
        ->and($values)->not->toContain('existing-secret-password')
        ->and($values)->not->toContain($existingUser->password)
        ->and(collect($values)->filter(fn (string $value): bool => str_contains($value, '$2y$') || str_contains($value, '$argon')))->toBeEmpty()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty()
        ->and($gitStatus)->toBe([]);
});

it('signs a prepared lecturer in with the exported username and temporary password before forcing a password change', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $fixture = lecturerBulkPreparationFixture();
    $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);
    $credential = $result['credential_rows'][0];
    $lecturer = $fixture['lecturer']->fresh()->user;

    expect($credential['login_username'])->toBe($lecturer->login_username)
        ->and($credential['login_username'])->not->toBeEmpty()
        ->and(Hash::check($credential['temporary_password'], $lecturer->password))->toBeTrue();

    Livewire::test(Login::class)
        ->fillForm([
            'login_username' => $credential['login_username'],
            'password' => $credential['temporary_password'],
        ])
        ->call('authenticate')
        ->assertRedirect(route('password.force-change.form'));

    $this->assertAuthenticatedAs($lecturer);
    expect($lecturer->fresh()->must_change_password)->toBeTrue();
});

it('exports lecturer account success and error reports as xlsx without plaintext passwords', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    addBulkLecturerSlot($fixture, 212, 'مدرس ثان');
    User::factory()->create(['login_username' => 'lec000211', 'role' => 'course_lecturer']);

    $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);
    $temporaryPassword = $result['credential_rows'][0]['temporary_password'];
    $items = LecturerAccountGenerationItem::query()->with(['lecturer', 'user.roles'])->orderBy('id')->get();
    $successRows = $items
        ->whereIn('result', [
            LecturerAccountGenerationItem::RESULT_ACCOUNT_CREATED,
            LecturerAccountGenerationItem::RESULT_EXISTING_ACCOUNT,
            LecturerAccountGenerationItem::RESULT_ROLE_ADDED,
        ])
        ->map(fn (LecturerAccountGenerationItem $item): array => [
            'اسم المدرس' => $item->lecturer?->name,
            'اسم الدخول' => $item->login_username,
            'النتيجة' => $item->result,
            'الحساب المنشأ أو المعاد استخدامه' => __('lecturer-account-preparation.results.'.$item->result),
            'الدور' => $item->user?->hasRole('course_lecturer') ? 'course_lecturer' : '',
            'الملاحظة' => $item->message,
        ])
        ->values()
        ->all();
    $errorRows = $items
        ->whereIn('result', [LecturerAccountGenerationItem::RESULT_SKIPPED, LecturerAccountGenerationItem::RESULT_FAILED])
        ->map(fn (LecturerAccountGenerationItem $item): array => [
            'اسم المدرس' => $item->lecturer?->name,
            'اسم الدخول المقترح' => $item->login_username,
            'رمز الخطأ' => $item->error_code,
            'السبب بالعربية' => $item->message,
            'الإجراء المقترح' => __('lecturer-account-preparation.report_actions.default'),
        ])
        ->values()
        ->all();
    $successSheet = spreadsheetFromXlsxBytes(Excel::raw(LecturerAccountReportExport::success($successRows), ExcelWriter::XLSX))->getSheetByName('العمليات الناجحة');
    $errorSheet = spreadsheetFromXlsxBytes(Excel::raw(LecturerAccountReportExport::errors($errorRows), ExcelWriter::XLSX))->getSheetByName('الأخطاء والحالات المستبعدة');
    $successValues = spreadsheetCellValues($successSheet->getParent());
    $errorValues = spreadsheetCellValues($errorSheet->getParent());

    expect($successSheet)->not->toBeNull()
        ->and($errorSheet)->not->toBeNull()
        ->and($successSheet->getRightToLeft())->toBeTrue()
        ->and($errorSheet->getRightToLeft())->toBeTrue()
        ->and($successSheet->getCell('A1')->getValue())->toBe('اسم المدرس')
        ->and($errorSheet->getCell('A1')->getValue())->toBe('اسم المدرس')
        ->and($successValues)->not->toContain($temporaryPassword)
        ->and($errorValues)->not->toContain($temporaryPassword)
        ->and(collect([...$successValues, ...$errorValues])->filter(fn (string $value): bool => str_contains($value, '$2y$') || str_contains($value, '$argon')))->toBeEmpty();
});

it('blocks username collisions with an existing login username', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    User::factory()->create([
        'login_username' => 'lec000211',
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);

    $preview = app(LecturerAccountPreparationService::class)->previewBulkPreparation($fixture['term']);

    expect($preview['accounts_to_create_count'])->toBe(1)
        ->and($preview['blocked_count'])->toBe(0)
        ->and($preview['rows'][0]['login_username'])->toBeNull();
});

it('resolves only login usernames for authentication', function (): void {
    $user = User::factory()->create(['login_username' => 'shared-login', 'role' => 'course_lecturer']);

    expect(app(PinLoginService::class)->findUserForLogin('shared-login')?->id)->toBe($user->id)
        ->and(app(PinLoginService::class)->findUserForLogin('S100'))->toBeNull();
});

it('allows actual filament login using login usernames only', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);
    $lecturer = User::factory()->create([
        'login_username' => 'lec000211',
        'password' => Hash::make('temporary-password'),
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $lecturer->assignRole('course_lecturer');
    $admin = lecturerAccountPreparationAdmin();
    $admin->forceFill(['password' => Hash::make('admin-password')])->save();
    $secondLecturer = User::factory()->create([
        'login_username' => 'lec000212',
        'password' => Hash::make('student-password'),
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $secondLecturer->assignRole('course_lecturer');

    Livewire::test(Login::class)
        ->fillForm(['login_username' => 'lec000211', 'password' => 'temporary-password'])
        ->call('authenticate');

    $this->assertAuthenticatedAs($lecturer);
    auth()->logout();

    Livewire::test(Login::class)
        ->fillForm(['login_username' => $admin->login_username, 'password' => 'admin-password'])
        ->call('authenticate');

    $this->assertAuthenticatedAs($admin);
    auth()->logout();

    Livewire::test(Login::class)
        ->fillForm(['login_username' => 'lec000212', 'password' => 'student-password'])
        ->call('authenticate');

    $this->assertAuthenticatedAs($secondLecturer);
});

it('generates unique credentials only for new users and is idempotent for existing links', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    addBulkLecturerSlot($fixture, 212, 'مدرس ثان');

    $first = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);
    $second = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);

    expect($first['created_account_count'])->toBe(2)
        ->and(collect($first['credential_rows'])->pluck('temporary_password')->unique())->toHaveCount(2)
        ->and($second['created_account_count'])->toBe(0)
        ->and($second['credential_rows'])->toBe([])
        ->and(User::query()->where('login_username', 'like', '%lec%')->count())->toBe(0)
        ->and(User::query()->whereNotNull('login_username')->count())->toBe(2)
        ->and(Lecturer::query()->whereNotNull('user_id')->count())->toBe(2);
});

it('adds a missing role without resetting an existing linked account password', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    $user = User::factory()->create([
        'login_username' => 'existing-linked-user',
        'password' => Hash::make('keep-this-password'),
        'role' => 'admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    $fixture['lecturer']->forceFill(['user_id' => $user->id])->save();

    $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);

    expect($result['granted_role_count'])->toBe(1)
        ->and(Hash::check('keep-this-password', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->hasRole('course_lecturer'))->toBeTrue()
        ->and($result['credential_rows'])->toBe([]);
});

it('commits successful lecturers when another lecturer is blocked', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    addBulkLecturerSlot($fixture, 212, 'مدرس ثان');
    User::factory()->create(['login_username' => 'unrelated-user', 'role' => 'course_lecturer']);

    $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);

    expect($result['created_account_count'])->toBe(2)
        ->and($result['blocked_count'])->toBe(0)
        ->and(User::query()->where('login_username', 'like', '%lec%')->count())->toBe(0);
});

it('resets temporary passwords with audit and does not reveal old passwords', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);
    $lecturer = $fixture['lecturer']->fresh();
    $oldHash = $lecturer->user->password;

    $result = app(LecturerAccountPreparationService::class)->resetTemporaryPasswords($fixture['term'], collect([$lecturer]));

    expect($result['credential_rows'])->toHaveCount(1)
        ->and($lecturer->user->fresh()->password)->not->toBe($oldHash)
        ->and($lecturer->user->fresh()->must_change_password)->toBeTrue()
        ->and(LecturerAccountGenerationItem::query()->where('message', __('lecturer-account-preparation.results.temporary_password_reset'))->exists())->toBeTrue();
});

it('uses password reset rather than recovering old temporary passwords after a lost download', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    $first = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);
    $lecturer = $fixture['lecturer']->fresh();
    $oldPlain = $first['credential_rows'][0]['temporary_password'];

    $reset = app(LecturerAccountPreparationService::class)->resetTemporaryPasswords($fixture['term'], collect([$lecturer]));
    $newPlain = $reset['credential_rows'][0]['temporary_password'];

    expect($newPlain)->not->toBe($oldPlain)
        ->and(Hash::check($oldPlain, $lecturer->user->fresh()->password))->toBeFalse()
        ->and(Hash::check($newPlain, $lecturer->user->fresh()->password))->toBeTrue()
        ->and(DB::table('lecturer_account_generation_items')->where('message', 'like', '%'.$oldPlain.'%')->exists())->toBeFalse()
        ->and(DB::table('lecturer_account_generation_runs')->where('summary', 'like', '%'.$oldPlain.'%')->exists())->toBeFalse();
});

it('recovers an interrupted bulk generation by resetting undelivered created accounts and creating remaining accounts', function (): void {
    $fixture = lecturerBulkPreparationFixture();
    addBulkLecturerSlot($fixture, 212, 'مدرس ثان');
    $lostPlain = 'lost-temporary-password';
    $linkedUser = User::factory()->create([
        'name' => 'محمد ابراهيم علي',
        'login_username' => 'lec000211',
        'password' => Hash::make($lostPlain),
        'must_change_password' => true,
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $linkedUser->assignRole(Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']));
    $fixture['lecturer']->forceFill(['user_id' => $linkedUser->id])->save();
    $staleRun = LecturerAccountGenerationRun::query()->create([
        'academic_term_id' => $fixture['term']->id,
        'status' => LecturerAccountGenerationRun::STATUS_PROCESSING,
        'lecturer_count' => 2,
        'started_at' => now(),
    ]);
    LecturerAccountGenerationItem::query()->create([
        'run_id' => $staleRun->id,
        'lecturer_id' => $fixture['lecturer']->id,
        'user_id' => $linkedUser->id,
        'login_username' => 'lec000211',
        'result' => LecturerAccountGenerationItem::RESULT_ACCOUNT_CREATED,
        'message' => __('lecturer-account-preparation.results.account_created'),
    ]);

    $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($fixture['term']);

    expect($result['created_account_count'])->toBe(1)
        ->and($result['recovered_password_reset_count'])->toBe(1)
        ->and($result['credential_rows'])->toHaveCount(2)
        ->and(collect($result['credential_rows'])->pluck('login_username')->every(fn (string $username): bool => (bool) preg_match('/^[a-z]+\d+$/', $username)))->toBeTrue()
        ->and(Hash::check($lostPlain, $linkedUser->fresh()->password))->toBeFalse()
        ->and($staleRun->fresh()->status)->toBe(LecturerAccountGenerationRun::STATUS_COMPLETED_WITH_ERRORS)
        ->and(LecturerAccountGenerationItem::query()->where('result', LecturerAccountGenerationItem::RESULT_TEMPORARY_PASSWORD_RESET)->count())->toBe(1)
        ->and(User::query()->whereNotNull('login_username')->count())->toBe(2)
        ->and(Lecturer::query()->whereNotNull('user_id')->count())->toBe(2)
        ->and(DB::table('lecturer_account_generation_items')->where('message', 'like', '%'.$lostPlain.'%')->exists())->toBeFalse()
        ->and(DB::table('lecturer_account_generation_runs')->where('summary', 'like', '%'.$lostPlain.'%')->exists())->toBeFalse();
});

it('forces password change before protected access and clears the flag after success', function (): void {
    $user = User::factory()->create([
        'must_change_password' => true,
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']));

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('password.force-change.form'));

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/login');

    $this->actingAs($user)
        ->put(route('password.force-change.update'), [
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertRedirect('/admin');

    expect($user->fresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

it('shows the dedicated bulk account preparation action without individual account actions', function (): void {
    lecturerBulkPreparationFixture();

    Livewire::actingAs(lecturerAccountPreparationAdmin())
        ->test(LecturerAccountPreparation::class)
        ->assertTableHeaderActionsExistInOrder([
            'create-bulk-lecturer-accounts',
            'preview-bulk-lecturer-account-preparation',
        ])
        ->assertTableActionDoesNotExist('create-login-account')
        ->assertTableActionDoesNotExist('link-existing-account')
        ->assertTableActionDoesNotExist('grant-course-lecturer-role');
});

it('rejects duplicate lecturer links and prevents one user from being linked to multiple lecturers', function (): void {
    $service = app(LecturerAccountPreparationService::class);
    $firstLecturer = arabicLecturerIdentity(['name' => 'د. سارة منصور', 'canonical_name' => 'د. سارة منصور']);
    $secondLecturer = arabicLecturerIdentity(['name' => 'د. ليلى حسن', 'canonical_name' => 'د. ليلى حسن']);
    $user = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);

    lecturerAccountPreparationAdmin();

    $service->linkExistingAccount($firstLecturer, $user);

    expect(fn () => $service->linkExistingAccount($firstLecturer->fresh(), User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
    ])))->toThrow(ValidationException::class)
        ->and(fn () => $service->linkExistingAccount($secondLecturer, $user))->toThrow(ValidationException::class);
});

it('grants the course lecturer role to a linked account', function (): void {
    $lecturer = arabicLecturerIdentity();
    $user = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    lecturerAccountPreparationAdmin();
    app(LecturerAccountPreparationService::class)->linkExistingAccount($lecturer, $user);

    expect($user->fresh()->hasRole('course_lecturer'))->toBeFalse();

    app(LecturerAccountPreparationService::class)->grantCourseLecturerRole($lecturer->fresh());

    expect($user->fresh()->hasRole('course_lecturer'))->toBeTrue()
        ->and($user->fresh()->role)->toBe('course_lecturer')
        ->and($user->fresh()->type)->toBe('lecturer');
});
