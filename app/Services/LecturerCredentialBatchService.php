<?php

namespace App\Services;

use App\Exports\LecturerLoginCredentialsExport;
use App\Models\AcademicTerm;
use App\Models\LecturerCredentialBatch;
use App\Models\LecturerCredentialBatchAction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class LecturerCredentialBatchService
{
    public function create(string $type, array $rows, ?AcademicTerm $term, ?User $actor): LecturerCredentialBatch
    {
        $staged = $this->stageExport(new LecturerLoginCredentialsExport($rows), 'بيانات-دخول-المحاضرين-'.now()->format('Ymd-His').'.xlsx');

        return $this->createFromStaged($type, $staged, count($rows), $term, $actor);
    }

    /** @return array{original_filename: string, encrypted_file_path: string, sha256: string, encryption_key_version: string} */
    public function stageExport(object $export, string $filename): array
    {
        $plain = Excel::raw($export, ExcelWriter::XLSX);
        $key = $this->key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Credential workbook encryption failed.');
        }
        $path = 'lecturer-credentials/'.Str::uuid().'.enc';
        Storage::disk('local')->put($path, $iv.$tag.$cipher);

        if (! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('Credential workbook encryption storage failed.');
        }

        return [
            'original_filename' => $filename,
            'encrypted_file_path' => $path,
            'sha256' => hash('sha256', $plain),
            'encryption_key_version' => (string) config('services.lecturer_credentials.key_version'),
        ];
    }

    /** @param array{original_filename: string, encrypted_file_path: string, sha256: string, encryption_key_version: string} $staged */
    public function createFromStaged(string $type, array $staged, int $recordCount, ?AcademicTerm $term, ?User $actor): LecturerCredentialBatch
    {
        return LecturerCredentialBatch::query()->create([
            'academic_term_id' => $term?->id, 'batch_type' => $type, 'original_filename' => $staged['original_filename'],
            'encrypted_file_path' => $staged['encrypted_file_path'], 'sha256' => $staged['sha256'], 'record_count' => $recordCount,
            'generated_by' => $actor?->id, 'generated_at' => now(), 'status' => 'available', 'encryption_key_version' => $staged['encryption_key_version'],
        ]);
    }

    public function discardStaged(string $path): void
    {
        if (str_starts_with($path, 'lecturer-credentials/')) {
            Storage::disk('local')->delete($path);
        }
    }

    public function decryptedContents(LecturerCredentialBatch $batch): string
    {
        $payload = Storage::disk('local')->get($batch->encrypted_file_path);
        if ($batch->encryption_key_version !== config('services.lecturer_credentials.key_version')) {
            throw new RuntimeException('Credential encryption key version is unavailable.');
        }
        $key = $this->key();
        $plain = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($payload, 0, 12), substr($payload, 12, 16));
        if ($plain === false || hash('sha256', $plain) !== $batch->sha256) {
            throw new RuntimeException('Credential workbook integrity check failed.');
        }

        return $plain;
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
        } $path = (string) $batch->encrypted_file_path;
        if (! str_starts_with($path, 'lecturer-credentials/') || ! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('Credential batch file is unavailable.');
        }

        if (! Storage::disk('local')->delete($path)) {
            throw new RuntimeException('Credential batch file could not be deleted.');
        }

        $batch->forceFill(['status' => 'deleted', 'deleted_at' => now(), 'deleted_by' => $actor?->id, 'encrypted_file_path' => null])->save();
        $this->audit($batch, 'deleted', $actor);
    }

    private function key(): string
    {
        $value = (string) config('services.lecturer_credentials.key');
        if (strlen($value) < 32) {
            throw new RuntimeException('Credential encryption key is unavailable.');
        }

        return hash('sha256', $value, true);
    }
}
