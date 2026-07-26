<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturerAccountGenerationItem extends Model
{
    public const RESULT_EXISTING_ACCOUNT = 'existing_account';

    public const RESULT_ACCOUNT_CREATED = 'account_created';

    public const RESULT_ROLE_ADDED = 'role_added';

    public const RESULT_USERNAME_ASSIGNED = 'username_assigned';

    public const RESULT_TEMPORARY_PASSWORD_RESET = 'temporary_password_reset';

    public const RESULT_SKIPPED = 'skipped';

    public const RESULT_FAILED = 'failed';

    protected $fillable = [
        'run_id',
        'lecturer_id',
        'user_id',
        'login_username',
        'result',
        'error_code',
        'message',
    ];

    /** @return BelongsTo<LecturerAccountGenerationRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(LecturerAccountGenerationRun::class, 'run_id');
    }

    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
