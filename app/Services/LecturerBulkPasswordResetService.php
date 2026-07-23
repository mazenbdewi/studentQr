<?php

namespace App\Services;

use App\Exports\LecturerPasswordResetCredentialsExport;
use App\Models\AcademicTerm;
use App\Models\LecturerCredentialBatch;
use App\Models\LecturerPasswordResetOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class LecturerBulkPasswordResetService
{
    public function __construct(private readonly LecturerCredentialBatchService $batches) {}

    /** @param \Illuminate\Support\Collection<int, mixed>|array<int, mixed> $userIds
     * @return array<string, mixed>
     */
    public function preview(\Illuminate\Support\Collection|array $userIds, ?AcademicTerm $term = null): array
    {
        $selected = collect($userIds)->map(fn (mixed $id): mixed => $id)->values();
        $validIds = $selected->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();
        $users = User::withTrashed()->with(['lecturerIdentities', 'roles'])->whereIn('id', $validIds)->get()->keyBy('id');
        $rows = [];

        foreach ($selected as $rawId) {
            $id = filter_var($rawId, FILTER_VALIDATE_INT) !== false ? (int) $rawId : null;
            if ($id === null || $id < 1) {
                $rows[] = $this->excludedRow(null, null, 'الحساب لم يعد موجودًا');

                continue;
            }

            $user = $users->get($id);
            if (! $user instanceof User) {
                $rows[] = $this->excludedRow($id, null, 'الحساب لم يعد موجودًا');

                continue;
            }

            $lecturer = $user->lecturerIdentities->first();
            $reason = match (true) {
                $user->trashed() => 'الحساب لم يعد موجودًا',
                ! $lecturer => 'لا يوجد ارتباط بهوية محاضر',
                ! $this->isActive($user) => 'الحساب غير فعال',
                ! $user->hasRole('course_lecturer') => 'الحساب لا يملك صلاحية مدرس مقرر',
                blank($user->login_username) => 'اسم الدخول غير جاهز',
                default => null,
            };
            $rows[] = $reason === null
                ? $this->eligibleRow($user, $lecturer->id, $lecturer->name)
                : $this->excludedRow($user->id, $lecturer?->id, $reason, $user, $lecturer?->name);
        }

        $eligible = collect($rows)->where('eligible', true)->values();
        $fingerprint = $this->fingerprint($eligible, $term);

        return [
            'rows' => $rows,
            'selected_count' => $selected->count(),
            'eligible_count' => $eligible->count(),
            'excluded_count' => collect($rows)->where('eligible', false)->count(),
            'already_forced_change_count' => $eligible->where('must_change_password', true)->count(),
            'fingerprint' => $fingerprint,
            'proposed_batch_filename' => $this->filename($term),
            'username_migration_state' => 'منفصلة عن إعادة ضبط كلمات المرور',
            'action_type' => 'password_reset',
            'academic_term_id' => $term?->id,
        ];
    }

    /** @param array<string, mixed> $preview
     * @return array{batch: LecturerCredentialBatch, reset_count: int}
     */
    public function execute(array $preview, string $approvedFingerprint, User $actor, ?AcademicTerm $term = null): array
    {
        $this->authorize($actor);
        if (! hash_equals((string) ($preview['fingerprint'] ?? ''), $approvedFingerprint)) {
            throw new RuntimeException('تم رفض المعاينة لأنها لم تعد صالحة.');
        }

        $ids = collect($preview['rows'] ?? [])->where('eligible', true)->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();
        if ($ids->isEmpty()) {
            throw new RuntimeException('لا توجد حسابات محاضرين مؤهلة لإعادة الضبط.');
        }
        if (LecturerPasswordResetOperation::query()->where('fingerprint', $approvedFingerprint)->exists()) {
            throw new RuntimeException('تم استخدام هذه المعاينة مسبقًا.');
        }

        $freshPreview = $this->preview($ids, $term);
        if (! hash_equals($approvedFingerprint, $freshPreview['fingerprint']) || $freshPreview['eligible_count'] !== $ids->count()) {
            throw new RuntimeException('تغيرت بيانات الحساب بعد المعاينة.');
        }

        $passwords = [];
        $credentialRows = [];
        foreach ($freshPreview['rows'] as $row) {
            if (! $row['eligible']) {
                continue;
            }
            $password = $this->temporaryPassword((string) $row['login_username']);
            $passwords[(int) $row['user_id']] = $password;
            $credentialRows[] = [
                'lecturer_name' => $row['lecturer_name'],
                'login_username' => $row['login_username'],
                'temporary_password' => $password,
                'must_change_password' => true,
            ];
        }

        $staged = $this->batches->stageExport(new LecturerPasswordResetCredentialsExport($credentialRows), $this->filename($term));

        try {
            $result = DB::transaction(function () use ($ids, $approvedFingerprint, $freshPreview, $passwords, $staged, $actor, $term): array {
                if (LecturerPasswordResetOperation::query()->where('fingerprint', $approvedFingerprint)->lockForUpdate()->exists()) {
                    throw new RuntimeException('تم رفض تنفيذ متزامن لإعادة الضبط.');
                }

                /** @var Collection<int, User> $users */
                $users = User::query()->with(['lecturerIdentities', 'roles'])->whereIn('id', $ids)->lockForUpdate()->get();
                $lockedPreview = $this->preview($users->pluck('id'), $term);
                if ($users->count() !== $ids->count() || ! hash_equals($approvedFingerprint, $lockedPreview['fingerprint'])) {
                    throw new RuntimeException('تغيرت بيانات الحساب بعد المعاينة.');
                }

                LecturerPasswordResetOperation::query()->create([
                    'fingerprint' => $approvedFingerprint,
                    'academic_term_id' => $term?->id,
                    'performed_by' => $actor->id,
                    'selected_count' => (int) $freshPreview['selected_count'],
                    'eligible_count' => (int) $freshPreview['eligible_count'],
                    'excluded_count' => (int) $freshPreview['excluded_count'],
                    'status' => 'completed',
                    'safe_metadata' => ['selected_count' => $freshPreview['selected_count'], 'eligible_count' => $freshPreview['eligible_count']],
                    'completed_at' => now(),
                ]);

                foreach ($users as $user) {
                    $user->forceFill([
                        'password' => Hash::make($passwords[$user->id]),
                        'must_change_password' => true,
                        'remember_token' => Str::random(60),
                    ])->save();
                }

                $batch = $this->batches->createFromStaged('password_reset', $staged, $users->count(), $term, $actor);
                $this->batches->audit($batch, 'reset_batch_created', $actor, ['record_count' => $users->count(), 'filename' => $batch->original_filename]);
                $this->batches->audit($batch, 'generated', $actor, ['record_count' => $users->count()]);

                return ['batch' => $batch, 'reset_count' => $users->count()];
            });
        } catch (\Throwable $exception) {
            $this->batches->discardStaged($staged['encrypted_file_path']);
            throw $exception;
        } finally {
            foreach ($passwords as $id => $password) {
                $passwords[$id] = '';
            }
            unset($credentialRows, $passwords);
        }

        return $result;
    }

    /** @param \Illuminate\Support\Collection<int, array<string, mixed>> $eligible */
    private function fingerprint(\Illuminate\Support\Collection $eligible, ?AcademicTerm $term): string
    {
        return hash('sha256', json_encode([
            'type' => 'password_reset',
            'term' => $term?->id,
            'users' => $eligible->sortBy('user_id')->map(fn (array $row): array => [
                $row['user_id'], $row['login_username'], $row['updated_at'], $row['must_change_password'],
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    private function temporaryPassword(string $username): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%^&*';
        do {
            $password = $this->randomCharacter($upper).$this->randomCharacter($lower).$this->randomCharacter($digits).$this->randomCharacter($symbols);
            $all = $upper.$lower.$digits.$symbols;
            for ($i = 4; $i < 18; $i++) {
                $password .= $this->randomCharacter($all);
            }
            $characters = str_split($password);
            shuffle($characters);
            $password = implode('', $characters);
        } while (str_contains(strtolower($password), strtolower($username)));

        return $password;
    }

    private function randomCharacter(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    private function isActive(User $user): bool
    {
        return (bool) $user->is_active && $user->status === 'active';
    }

    /** @return array<string, mixed> */
    private function eligibleRow(User $user, int $lecturerId, string $lecturerName): array
    {
        return ['user_id' => $user->id, 'lecturer_id' => $lecturerId, 'lecturer_name' => $lecturerName, 'login_username' => $user->login_username, 'active' => true, 'must_change_password' => (bool) $user->must_change_password, 'eligible' => true, 'reason' => null, 'updated_at' => $user->updated_at?->toAtomString()];
    }

    /** @return array<string, mixed> */
    private function excludedRow(?int $id, ?int $lecturerId, string $reason, ?User $user = null, ?string $lecturerName = null): array
    {
        return ['user_id' => $id, 'lecturer_id' => $lecturerId, 'lecturer_name' => $lecturerName, 'login_username' => $user?->login_username, 'active' => $user ? $this->isActive($user) : false, 'must_change_password' => (bool) ($user?->must_change_password), 'eligible' => false, 'reason' => $reason, 'updated_at' => $user?->updated_at?->toAtomString()];
    }

    private function filename(?AcademicTerm $term): string
    {
        $termPart = $term ? '-'.trim((string) preg_replace('/[^\pL\pN]+/u', '-', $term->display_name), '-') : '';

        return 'بيانات-دخول-المحاضرين-إعادة-ضبط'.$termPart.'-'.now()->format('Ymd-His').'.xlsx';
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasRole('super-admin') && ! $actor->can('reset lecturer passwords')) {
            throw new RuntimeException('غير مصرح بإعادة ضبط كلمات مرور المحاضرين.');
        }
    }
}
