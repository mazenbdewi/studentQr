<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Lecturer;
use App\Models\LecturerAccountGenerationItem;
use App\Models\LecturerAccountGenerationRun;
use App\Models\LectureSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class LecturerAccountPreparationService
{
    public function __construct(
        private readonly SubjectSectionLecturerSynchronizationService $sectionLecturerSynchronization,
        private readonly LecturerUsernameGenerator $usernameGenerator,
    ) {}

    public function loginUsernameForLecturer(Lecturer $lecturer): string
    {
        $proposal = $this->usernameGenerator->proposal($lecturer);

        if (($proposal['requires_manual_review'] ?? true) || ($proposal['duplicate'] ?? false) || blank($proposal['proposed_username'])) {
            throw ValidationException::withMessages(['login_username' => 'يتطلب اسم الدخول مراجعة إدارية قبل إنشاء الحساب.']);
        }

        return $proposal['proposed_username'];
    }

    public function previewBulkPreparation(AcademicTerm $term): array
    {
        /** @var Collection<int, Lecturer> $lecturers */
        $lecturers = Lecturer::query()
            ->with(['user.roles'])
            ->whereHas('scheduleSlots', fn ($query) => $query->where('academic_term_id', $term->id))
            ->withCount(['scheduleSlots as current_term_weekly_slots_count' => fn ($query) => $query->where('academic_term_id', $term->id)])
            ->orderBy('name')
            ->get();

        $rows = $lecturers->map(function ($lecturer): array {
            /** @var Lecturer $lecturer */
            /** @var User|null $user */
            $user = $lecturer->user;

            $status = $this->bulkPreparationStatus($lecturer);
            $loginUsername = $user?->login_username;
            $blockedReason = null;

            if ($status === 'needs_login_username') {
                try {
                    $loginUsername = $this->loginUsernameForLecturer($lecturer);
                } catch (ValidationException) {
                    $status = 'blocked';
                    $blockedReason = 'invalid_login_username';
                    $loginUsername = null;
                }
            }

            if ($status === 'needs_create' && $loginUsername !== null && $this->loginIdentifierExists($loginUsername)) {
                $status = 'blocked';
                $blockedReason = 'login_identifier_already_exists';
            }

            return [
                'lecturer_id' => (int) $lecturer->id,
                'lecturer_name' => (string) $lecturer->name,
                'weekly_slot_count' => (int) $lecturer->getAttribute('current_term_weekly_slots_count'),
                'login_username' => $loginUsername,
                'linked_user_id' => $lecturer->user_id ? (int) $lecturer->user_id : null,
                'status' => $status,
                'blocked_reason' => $blockedReason,
            ];
        })->values();

        return [
            'academic_term_id' => (int) $term->id,
            'referenced_lecturer_count' => $rows->count(),
            'already_ready_count' => $rows->where('status', 'ready')->count(),
            'accounts_to_create_count' => $rows->where('status', 'needs_create')->count(),
            'roles_to_grant_count' => $rows->where('status', 'needs_course_lecturer_role')->count(),
            'blocked_count' => $rows->where('status', 'blocked')->count(),
            'rows' => $rows->all(),
            'success_report' => $rows
                ->whereIn('status', ['ready', 'needs_create', 'needs_course_lecturer_role'])
                ->values()
                ->all(),
            'error_report' => $rows
                ->where('status', 'blocked')
                ->values()
                ->all(),
        ];
    }

    public function prepareBulkAccounts(AcademicTerm $term, ?User $actor = null): array
    {
        $lock = Cache::lock('lecturer-account-preparation:'.$term->id, 600);

        if (! $lock->get()) {
            throw new \RuntimeException('توجد عملية إنشاء حسابات قيد التنفيذ حاليًا. يرجى الانتظار حتى اكتمالها.');
        }

        try {
            @set_time_limit(300);
            Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);

            $recoveryLecturerIds = $this->undeliveredTemporaryPasswordLecturerIds($term);
            $this->markStaleProcessingRunsForRecovery($term);
            $preview = $this->previewBulkPreparation($term);
            $run = LecturerAccountGenerationRun::query()->create([
                'academic_term_id' => $term->id,
                'started_by' => $actor?->id,
                'status' => LecturerAccountGenerationRun::STATUS_PROCESSING,
                'lecturer_count' => $preview['referenced_lecturer_count'],
                'started_at' => now(),
            ]);
            $credentials = [];
            $usedPlainPasswords = [];

            foreach ($preview['rows'] as $row) {
                try {
                    $result = DB::transaction(function () use ($row, $run, $recoveryLecturerIds, &$usedPlainPasswords): array {
                        return $this->processLecturerAccountRow($run, $row, $usedPlainPasswords, $recoveryLecturerIds);
                    });

                    if (($result['credential'] ?? null) !== null) {
                        $credentials[] = $result['credential'];
                    }
                } catch (\Throwable $exception) {
                    LecturerAccountGenerationItem::query()->create([
                        'run_id' => $run->id,
                        'lecturer_id' => $row['lecturer_id'],
                        'login_username' => $row['login_username'] ?? null,
                        'result' => LecturerAccountGenerationItem::RESULT_FAILED,
                        'error_code' => 'unexpected_item_failure',
                        'message' => __('lecturer-account-preparation.errors.unexpected_item_failure'),
                    ]);
                }
            }

            $after = $this->previewBulkPreparation($term);
            $this->completeRun($run);

            return [
                ...$after,
                'generation_run_id' => (int) $run->id,
                'created_account_count' => (int) $run->fresh()->created_count,
                'granted_role_count' => (int) $run->fresh()->role_added_count,
                'recovered_password_reset_count' => (int) $run->items()
                    ->where('result', LecturerAccountGenerationItem::RESULT_TEMPORARY_PASSWORD_RESET)
                    ->count(),
                'credential_rows' => $credentials,
                'error_report' => $run->items()
                    ->where('result', LecturerAccountGenerationItem::RESULT_FAILED)
                    ->get()
                    ->map(fn (LecturerAccountGenerationItem $item): array => $item->only(['lecturer_id', 'login_username', 'error_code', 'message']))
                    ->all(),
            ];
        } finally {
            $lock->release();
        }
    }

    /** @param iterable<int, Lecturer> $lecturers */
    public function resetTemporaryPasswords(AcademicTerm $term, iterable $lecturers, ?User $actor = null): array
    {
        @set_time_limit(300);
        Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);

        $lecturerCollection = collect($lecturers)->values();
        $run = LecturerAccountGenerationRun::query()->create([
            'academic_term_id' => $term->id,
            'started_by' => $actor?->id,
            'status' => LecturerAccountGenerationRun::STATUS_PROCESSING,
            'lecturer_count' => $lecturerCollection->count(),
            'started_at' => now(),
        ]);
        $credentials = [];
        $usedPlainPasswords = [];

        foreach ($lecturerCollection as $lecturer) {
            try {
                $credential = DB::transaction(function () use ($run, $lecturer, &$usedPlainPasswords): array {
                    $locked = Lecturer::query()->with('user.roles')->lockForUpdate()->findOrFail($lecturer->id);
                    $user = $locked->user;

                    if (! $user instanceof User) {
                        LecturerAccountGenerationItem::query()->create([
                            'run_id' => $run->id,
                            'lecturer_id' => $locked->id,
                            'result' => LecturerAccountGenerationItem::RESULT_FAILED,
                            'error_code' => 'missing_linked_user',
                            'message' => __('lecturer-account-preparation.errors.missing_linked_user'),
                        ]);

                        return [];
                    }

                    $credential = $this->resetTemporaryPasswordForLinkedLecturer($locked, $user, $usedPlainPasswords);
                    LecturerAccountGenerationItem::query()->create([
                        'run_id' => $run->id,
                        'lecturer_id' => $locked->id,
                        'user_id' => $user->id,
                        'login_username' => $user->login_username,
                        'result' => LecturerAccountGenerationItem::RESULT_TEMPORARY_PASSWORD_RESET,
                        'message' => __('lecturer-account-preparation.results.temporary_password_reset'),
                    ]);

                    return $credential;
                });

                if ($credential !== []) {
                    $credentials[] = $credential;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $this->completeRun($run);

        return [
            'generation_run_id' => (int) $run->id,
            'credential_rows' => $credentials,
        ];
    }

    public function createLoginAccount(Lecturer $lecturer, string $email, string $password, string $passwordConfirmation): User
    {
        $this->ensureLecturerIsUnlinked($lecturer);
        $this->ensurePasswordConfirmed($password, $passwordConfirmation);
        $this->ensureEmailIsUnique($email);

        return DB::transaction(function () use ($lecturer, $email, $password): User {
            $user = User::query()->create([
                'name' => $lecturer->name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'course_lecturer',
                'type' => 'lecturer',
                'status' => 'active',
                'is_active' => true,
                'title' => $lecturer->title,
            ]);

            $lecturer->forceFill(['user_id' => $user->id])->save();
            $lecturer->setRelation('user', $user);
            $this->ensureLoginUsername($lecturer, $user);

            $user->assignRole(User::mapDatabaseRoleToSpatieRole('course_lecturer'));
            $this->synchronizeLecturerSections($lecturer);

            return $user;
        });
    }

    public function linkExistingAccount(Lecturer $lecturer, User $user): Lecturer
    {
        $this->ensureLecturerIsUnlinked($lecturer);
        $this->ensureUserCanBeLinked($user);

        $lecturer->forceFill(['user_id' => $user->id])->save();
        $this->synchronizeLecturerSections($lecturer);

        return $lecturer->refresh();
    }

    public function grantCourseLecturerRole(Lecturer $lecturer): User
    {
        $user = $lecturer->user;

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'user_id' => __('lecturer-account-preparation.validation.no_linked_user'),
            ]);
        }

        $user->forceFill([
            'role' => 'course_lecturer',
            'type' => 'lecturer',
        ])->save();

        $user->assignRole(User::mapDatabaseRoleToSpatieRole('course_lecturer'));
        $this->synchronizeLecturerSections($lecturer);

        return $user->refresh();
    }

    public function readinessStatus(Lecturer $lecturer): string
    {
        $user = $lecturer->user;

        if (! $lecturer->user_id) {
            return 'missing_account';
        }

        if (! $user instanceof User) {
            return 'broken_link';
        }

        if (Lecturer::query()
            ->where('user_id', $user->id)
            ->whereKeyNot($lecturer->id)
            ->exists()) {
            return 'duplicate_link';
        }

        if ($user->trashed() || ! ($user->is_active ?? true) || $user->status !== 'active') {
            return 'inactive_account';
        }

        if (! $user->hasRole('course_lecturer')) {
            return 'missing_course_lecturer_role';
        }

        if (blank($user->login_username)) {
            return 'missing_login_username';
        }

        return 'ready';
    }

    public function hasLinkedUserWithCourseLecturerRole(Lecturer $lecturer): bool
    {
        return $this->readinessStatus($lecturer) === 'ready';
    }

    public function lecturerSessionCount(): int
    {
        return LectureSession::query()->count();
    }

    private function bulkPreparationStatus(Lecturer $lecturer): string
    {
        $status = $this->readinessStatus($lecturer);

        return match ($status) {
            'ready' => 'ready',
            'missing_account' => 'needs_create',
            'missing_course_lecturer_role' => 'needs_course_lecturer_role',
            'missing_login_username' => 'needs_login_username',
            default => 'blocked',
        };
    }

    private function processLecturerAccountRow(LecturerAccountGenerationRun $run, array $row, array &$usedPlainPasswords, Collection $recoveryLecturerIds): array
    {
        $lecturer = Lecturer::query()->with('user.roles')->lockForUpdate()->findOrFail($row['lecturer_id']);

        if ($recoveryLecturerIds->contains((int) $lecturer->id) && $lecturer->user instanceof User) {
            $credential = $this->resetTemporaryPasswordForLinkedLecturer($lecturer, $lecturer->user, $usedPlainPasswords);
            LecturerAccountGenerationItem::query()->create([
                'run_id' => $run->id,
                'lecturer_id' => $lecturer->id,
                'user_id' => $lecturer->user->id,
                'login_username' => $lecturer->user->login_username,
                'result' => LecturerAccountGenerationItem::RESULT_TEMPORARY_PASSWORD_RESET,
                'message' => __('lecturer-account-preparation.results.temporary_password_reset_after_failed_download'),
            ]);

            return ['credential' => $credential];
        }

        if ($row['status'] === 'blocked') {
            LecturerAccountGenerationItem::query()->create([
                'run_id' => $run->id,
                'lecturer_id' => $lecturer->id,
                'login_username' => $row['login_username'] ?? null,
                'result' => LecturerAccountGenerationItem::RESULT_SKIPPED,
                'error_code' => $row['blocked_reason'] ?? 'blocked',
                'message' => __('lecturer-account-preparation.results.skipped'),
            ]);

            return [];
        }

        if ($row['status'] === 'ready') {
            LecturerAccountGenerationItem::query()->create([
                'run_id' => $run->id,
                'lecturer_id' => $lecturer->id,
                'user_id' => $lecturer->user_id,
                'login_username' => $lecturer->user instanceof User ? $lecturer->user->login_username : null,
                'result' => LecturerAccountGenerationItem::RESULT_EXISTING_ACCOUNT,
                'message' => __('lecturer-account-preparation.results.existing_account'),
            ]);

            return [];
        }

        if ($row['status'] === 'needs_course_lecturer_role') {
            $user = $this->grantCourseLecturerRole($lecturer);
            LecturerAccountGenerationItem::query()->create([
                'run_id' => $run->id,
                'lecturer_id' => $lecturer->id,
                'user_id' => $user->id,
                'login_username' => $user->login_username,
                'result' => LecturerAccountGenerationItem::RESULT_ROLE_ADDED,
                'message' => __('lecturer-account-preparation.results.role_added'),
            ]);

            return [];
        }

        if ($row['status'] === 'needs_login_username') {
            $user = $lecturer->user;

            if (! $user instanceof User) {
                throw new \RuntimeException('The lecturer account link is no longer available.');
            }

            $loginUsername = $this->ensureLoginUsername($lecturer, $user);
            LecturerAccountGenerationItem::query()->create([
                'run_id' => $run->id,
                'lecturer_id' => $lecturer->id,
                'user_id' => $user->id,
                'login_username' => $loginUsername,
                'result' => LecturerAccountGenerationItem::RESULT_USERNAME_ASSIGNED,
                'message' => __('lecturer-account-preparation.results.username_assigned'),
            ]);

            return [];
        }

        $temporaryPassword = $this->newTemporaryPassword($usedPlainPasswords);
        $user = User::query()->create([
            'name' => $lecturer->name,
            'email' => null,
            'login_username' => null,
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'role' => 'course_lecturer',
            'type' => 'lecturer',
            'status' => 'active',
            'is_active' => true,
            'title' => $lecturer->title,
        ]);
        $lecturer->forceFill(['user_id' => $user->id])->save();
        $lecturer->setRelation('user', $user);
        $loginUsername = $this->ensureLoginUsername($lecturer, $user);
        $user->assignRole(User::mapDatabaseRoleToSpatieRole('course_lecturer'));
        $this->synchronizeLecturerSections($lecturer);

        LecturerAccountGenerationItem::query()->create([
            'run_id' => $run->id,
            'lecturer_id' => $lecturer->id,
            'user_id' => $user->id,
            'login_username' => $loginUsername,
            'result' => LecturerAccountGenerationItem::RESULT_ACCOUNT_CREATED,
            'message' => __('lecturer-account-preparation.results.account_created'),
        ]);

        return [
            'credential' => $this->credentialRow($lecturer, $user, $temporaryPassword),
        ];
    }

    private function completeRun(LecturerAccountGenerationRun $run): void
    {
        $items = $run->items()->get();
        $failedCount = $items->where('result', LecturerAccountGenerationItem::RESULT_FAILED)->count();
        $skippedCount = $items->where('result', LecturerAccountGenerationItem::RESULT_SKIPPED)->count();
        $existingCount = $items->where('result', LecturerAccountGenerationItem::RESULT_EXISTING_ACCOUNT)->count();
        $createdCount = $items->where('result', LecturerAccountGenerationItem::RESULT_ACCOUNT_CREATED)->count();
        $roleAddedCount = $items->where('result', LecturerAccountGenerationItem::RESULT_ROLE_ADDED)->count();
        $temporaryPasswordResetCount = $items->where('result', LecturerAccountGenerationItem::RESULT_TEMPORARY_PASSWORD_RESET)->count();

        $run->update([
            'status' => $failedCount > 0 || $skippedCount > 0
                ? LecturerAccountGenerationRun::STATUS_COMPLETED_WITH_ERRORS
                : LecturerAccountGenerationRun::STATUS_COMPLETED,
            'existing_count' => $existingCount,
            'created_count' => $createdCount,
            'role_added_count' => $roleAddedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'summary' => [
                'existing_count' => $existingCount,
                'created_count' => $createdCount,
                'role_added_count' => $roleAddedCount,
                'temporary_password_reset_count' => $temporaryPasswordResetCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
            ],
            'completed_at' => now(),
        ]);
    }

    private function synchronizeLecturerSections(Lecturer $lecturer): void
    {
        $this->sectionLecturerSynchronization->synchronizeSections(
            $lecturer->scheduleSlots()->distinct()->pluck('subject_section_id'),
        );
    }

    private function ensureLoginIdentifierAvailable(string $identifier): void
    {
        if ($this->loginIdentifierExists($identifier)) {
            throw ValidationException::withMessages([
                'login_username' => __('lecturer-account-preparation.validation.login_identifier_taken'),
            ]);
        }
    }

    private function loginIdentifierExists(string $identifier): bool
    {
        return User::withTrashed()
            ->where(function ($query) use ($identifier): void {
                $query
                    ->where('login_username', $identifier)
                    ->orWhere('email', $identifier)
                    ->orWhere('student_number', $identifier);
            })
            ->exists();
    }

    private function newTemporaryPassword(array &$usedPlainPasswords): string
    {
        do {
            $password = strtr(rtrim(base64_encode(random_bytes(18)), '='), '+/', '-_');
        } while (in_array($password, $usedPlainPasswords, true));

        $usedPlainPasswords[] = $password;

        return $password;
    }

    private function credentialRow(Lecturer $lecturer, User $user, string $temporaryPassword): array
    {
        return [
            'lecturer_name' => (string) $lecturer->name,
            'login_username' => (string) $user->login_username,
            'temporary_password' => $temporaryPassword,
            'account_status' => (string) $user->status,
            'role' => 'course_lecturer',
            'must_change_password' => (bool) $user->must_change_password,
            'notes' => __('lecturer-account-preparation.results.account_created'),
        ];
    }

    private function resetTemporaryPasswordForLinkedLecturer(Lecturer $lecturer, User $user, array &$usedPlainPasswords): array
    {
        $this->ensureLoginUsername($lecturer, $user);
        $temporaryPassword = $this->newTemporaryPassword($usedPlainPasswords);
        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'status' => 'active',
            'is_active' => true,
        ])->save();
        $user->assignRole(User::mapDatabaseRoleToSpatieRole('course_lecturer'));

        return $this->credentialRow($lecturer, $user->fresh(), $temporaryPassword);
    }

    private function ensureLoginUsername(Lecturer $lecturer, User $user): string
    {
        $existing = strtolower(trim((string) $user->login_username));

        if ($existing !== '') {
            return $existing;
        }

        $lecturer->forceFill(['user_id' => $user->id]);
        $lecturer->setRelation('user', $user);
        $loginUsername = $this->loginUsernameForLecturer($lecturer);
        $this->ensureLoginIdentifierAvailable($loginUsername);
        $user->forceFill(['login_username' => $loginUsername])->save();

        return $loginUsername;
    }

    private function undeliveredTemporaryPasswordLecturerIds(AcademicTerm $term): Collection
    {
        return LecturerAccountGenerationItem::query()
            ->whereIn('run_id', LecturerAccountGenerationRun::query()
                ->where('academic_term_id', $term->id)
                ->where('status', LecturerAccountGenerationRun::STATUS_PROCESSING)
                ->select('id'))
            ->where('result', LecturerAccountGenerationItem::RESULT_ACCOUNT_CREATED)
            ->pluck('lecturer_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function markStaleProcessingRunsForRecovery(AcademicTerm $term): void
    {
        LecturerAccountGenerationRun::query()
            ->where('academic_term_id', $term->id)
            ->where('status', LecturerAccountGenerationRun::STATUS_PROCESSING)
            ->update([
                'status' => LecturerAccountGenerationRun::STATUS_COMPLETED_WITH_ERRORS,
                'summary' => json_encode([
                    'error' => 'credentials_download_failed_or_interrupted',
                    'recovery_action' => 'temporary_password_reset_required',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'completed_at' => now(),
            ]);
    }

    private function ensureLecturerIsUnlinked(Lecturer $lecturer): void
    {
        if ($lecturer->user_id) {
            throw ValidationException::withMessages([
                'user_id' => __('lecturer-account-preparation.validation.lecturer_already_linked'),
            ]);
        }
    }

    private function ensureUserCanBeLinked(User $user): void
    {
        if (Lecturer::query()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => __('lecturer-account-preparation.validation.user_already_linked'),
            ]);
        }
    }

    private function ensurePasswordConfirmed(string $password, string $passwordConfirmation): void
    {
        if ($password !== $passwordConfirmation) {
            throw ValidationException::withMessages([
                'password' => __('validation.confirmed', ['attribute' => __('lecturer-account-preparation.fields.password')]),
            ]);
        }
    }

    private function ensureEmailIsUnique(string $email): void
    {
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => __('lecturer-account-preparation.fields.email')]),
            ]);
        }
    }
}
