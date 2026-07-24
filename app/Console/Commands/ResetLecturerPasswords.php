<?php

namespace App\Console\Commands;

use App\Models\AcademicTerm;
use App\Models\Lecturer;
use App\Models\User;
use App\Services\LecturerBulkPasswordResetService;
use Illuminate\Console\Command;

class ResetLecturerPasswords extends Command
{
    protected $signature = 'lecturers:reset-passwords
        {--preview : Produce a zero-write eligibility preview (the default)}
        {--execute : Execute the reset; requires --fingerprint and --confirm=RESET_LECTURER_PASSWORDS}
        {--fingerprint= : Approved preview fingerprint required for execution}
        {--actor-id= : Authorized admin or super-admin user ID required for execution}
        {--term= : Optional academic term ID for the batch context}
        {--confirm= : Must equal RESET_LECTURER_PASSWORDS for execution}';

    protected $description = 'Preview or execute the failure-safe bulk lecturer password reset.';

    public function handle(LecturerBulkPasswordResetService $service): int
    {
        if ($this->option('preview') && $this->option('execute')) {
            $this->error('Choose either --preview or --execute, not both.');

            return self::INVALID;
        }

        $term = filled($this->option('term'))
            ? AcademicTerm::query()->findOrFail((int) $this->option('term'))
            : null;
        $userIds = Lecturer::query()->whereNotNull('user_id')->orderBy('user_id')->pluck('user_id');
        $preview = $service->preview($userIds, $term);

        $this->table(
            ['المحدد', 'المؤهل', 'المستبعد', 'المطلوب تغيير كلمة المرور', 'نوع العملية'],
            [[
                $preview['selected_count'],
                $preview['eligible_count'],
                $preview['excluded_count'],
                $preview['already_forced_change_count'],
                $preview['action_type'],
            ]],
        );

        if (! $this->option('execute')) {
            $this->line('Fingerprint: '.$preview['fingerprint']);
            $this->comment('هذه معاينة فقط؛ لم يتم إنشاء كلمات مرور أو ملفات أو سجلات قاعدة بيانات.');

            return self::SUCCESS;
        }

        if ($this->option('confirm') !== 'RESET_LECTURER_PASSWORDS') {
            $this->error('Execution requires --confirm=RESET_LECTURER_PASSWORDS.');

            return self::INVALID;
        }
        if (! filled($this->option('fingerprint')) || ! hash_equals($preview['fingerprint'], (string) $this->option('fingerprint'))) {
            $this->error('The supplied fingerprint is missing, stale, or does not match the current preview.');

            return self::FAILURE;
        }
        if (! filled($this->option('actor-id'))) {
            $this->error('Execution requires --actor-id for authorization and audit attribution.');

            return self::INVALID;
        }

        $actor = User::query()->findOrFail((int) $this->option('actor-id'));
        $result = $service->execute($preview, $preview['fingerprint'], $actor, $term);

        $this->info('Password reset completed safely.');
        $this->line('Reset accounts: '.$result['reset_count']);
        $this->line('Credential batch UUID: '.$result['batch']->uuid);
        $this->line('Credential records: '.$result['batch']->record_count);

        return self::SUCCESS;
    }
}
