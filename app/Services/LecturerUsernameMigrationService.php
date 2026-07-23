<?php

namespace App\Services;

use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LecturerUsernameMigrationService
{
    public function __construct(private readonly LecturerUsernameGenerator $generator, private readonly ActivityLogger $activityLogger) {}

    public function preview(): array
    {
        return Lecturer::query()->with('user')->whereNotNull('user_id')->orderBy('id')->get()->map(function (Lecturer $lecturer): array {
            $proposal = $this->generator->proposal($lecturer);
            /** @var User|null $user */
            $user = $lecturer->user;

            return [...$proposal, 'arabic_name' => $lecturer->name, 'current_username' => $user?->login_username, 'linked_user_id' => $lecturer->user_id, 'account_status' => $user?->status];
        })->all();
    }

    /** @param array<int, array{lecturer_id:int, username:string}> $approved */
    public function apply(array $approved, ?User $actor = null): void
    {
        DB::transaction(function () use ($approved, $actor): void {
            foreach ($approved as $row) {
                $lecturer = Lecturer::query()->with('user')->lockForUpdate()->findOrFail($row['lecturer_id']);
                if (! $lecturer->user instanceof User) {
                    continue;
                }
                $old = $lecturer->user->login_username;
                abort_if(User::withTrashed()->where('login_username', $row['username'])->whereKeyNot($lecturer->user->id)->exists(), 422, 'اسم الدخول مستخدم بالفعل.');
                $lecturer->user->forceFill(['login_username' => $row['username']])->save();
                $this->activityLogger->log(['category' => 'lecturer_accounts', 'action' => 'username_migrated', 'model_type' => User::class, 'model_id' => $lecturer->user->id, 'old_values' => ['login_username' => $old], 'new_values' => ['login_username' => $row['username']], 'context' => ['lecturer_id' => $lecturer->id, 'actor_id' => $actor?->id]]);
            }
        });
    }
}
