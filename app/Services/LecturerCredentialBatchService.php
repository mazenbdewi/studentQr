<?php

namespace App\Services;

use App\Exports\LecturerAccountReportExport;
use App\Exports\LecturerPasswordResetCredentialsExport;
use App\Models\AcademicTerm;
use App\Models\LecturerCredentialBatch;
use App\Models\LecturerCredentialBatchAction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class LecturerCredentialBatchService
{
    /** @param array<int, int> $userIds */
    public function create(string $type, array $userIds, ?AcademicTerm $term, ?User $actor, ?string $filename = null): LecturerCredentialBatch
    {
        $ids = collect($userIds)->filter(fn (int $id): bool => $id > 0)->unique()->values()->all();

        return LecturerCredentialBatch::query()->create([
            'academic_term_id' => $term?->id,
            'batch_type' => $type,
            'original_filename' => $filename ?? 'بيانات-دخول-المحاضرين-'.now()->format('Ymd-His').'.xlsx',
            'encrypted_file_path' => null,
            'sha256' => hash('sha256', json_encode($ids, JSON_THROW_ON_ERROR)),
            'encryption_key_version' => 'current-state',
            'recipient_user_ids' => $ids,
            'record_count' => count($ids),
            'generated_by' => $actor?->id,
            'generated_at' => now(),
            'status' => 'available',
        ]);
    }

    public function exportForBatch(LecturerCredentialBatch $batch): LecturerPasswordResetCredentialsExport
    {
        if ($batch->status !== 'available' || ! is_array($batch->recipient_user_ids)) {
            throw new RuntimeException('لا يمكن تنزيل ملف بيانات دخول تاريخي؛ يلزم إنشاء تقرير من حالة الحسابات الحالية.');
        }

        $ids = $batch->recipient_user_ids;
        $users = User::query()->with(['lecturerIdentities', 'roles'])->whereIn('id', $ids)->get()->keyBy('id');
        $rows = [];

        foreach ($ids as $id) {
            /** @var User|null $user */
            $user = $users->get($id);
            $rows[] = [
                'lecturer_name' => $user instanceof User ? ($user->lecturerIdentities->pluck('name')->first() ?? $user->name) : 'حساب غير متاح',
                'login_username' => $user instanceof User ? ($user->login_username ?? '') : '',
                'temporary_password' => LecturerAccountReportExport::temporaryPasswordForReport($user),
                'must_change_password' => (bool) $user?->must_change_password,
                'result' => 'تمت إعادة تعيين كلمة المرور',
                'role' => $user instanceof User && $user->hasRole('course_lecturer') ? 'course_lecturer' : '',
                'note' => $user?->must_change_password ? 'كلمة المرور المؤقتة الحالية' : 'تغيرت كلمة المرور بعد إنشاء الدفعة',
            ];
        }

        return new LecturerPasswordResetCredentialsExport($rows);
    }

    /** The legacy method now renders current account state; it never decrypts historical workbook snapshots. */
    public function decryptedContents(LecturerCredentialBatch $batch): string
    {
        return Excel::raw($this->exportForBatch($batch), ExcelWriter::XLSX);
    }

    public function recordDownload(LecturerCredentialBatch $batch): void
    {
        $batch->increment('downloaded_count');
        $batch->forceFill(['last_downloaded_at' => now()])->save();
    }

    public function audit(LecturerCredentialBatch $batch, string $action, ?User $actor = null, array $metadata = []): void
    {
        LecturerCredentialBatchAction::query()->create(['lecturer_credential_batch_id' => $batch->id, 'action' => $action, 'performed_by' => $actor?->id, 'request_ip' => request()->ip(), 'safe_metadata' => array_intersect_key($metadata, array_flip(['filename', 'record_count', 'status'])), 'performed_at' => now()]);
    }

    public function delete(LecturerCredentialBatch $batch, ?User $actor = null): void
    {
        if ($batch->status === 'deleted') {
            return;
        }

        $path = (string) $batch->encrypted_file_path;
        if ($path !== '') {
            if (! str_starts_with($path, 'lecturer-credentials/') || ! Storage::disk('local')->exists($path)) {
                throw new RuntimeException('Credential batch file is unavailable.');
            }

            if (! Storage::disk('local')->delete($path)) {
                throw new RuntimeException('Credential batch file could not be deleted.');
            }
        }

        $batch->forceFill(['status' => 'deleted', 'deleted_at' => now(), 'deleted_by' => $actor?->id, 'encrypted_file_path' => null])->save();
        $this->audit($batch, 'deleted', $actor);
    }
}
