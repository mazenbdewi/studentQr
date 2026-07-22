<?php

namespace App\Services;

use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LecturerAccountPreparationService
{
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

            $user->assignRole(User::mapDatabaseRoleToSpatieRole('course_lecturer'));

            $lecturer->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    public function linkExistingAccount(Lecturer $lecturer, User $user): Lecturer
    {
        $this->ensureLecturerIsUnlinked($lecturer);
        $this->ensureUserCanBeLinked($user);

        $lecturer->forceFill(['user_id' => $user->id])->save();

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
