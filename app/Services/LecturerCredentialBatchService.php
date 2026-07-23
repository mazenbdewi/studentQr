<?php

namespace App\Services;

use App\Exports\LecturerLoginCredentialsExport;
use App\Models\AcademicTerm;
use App\Models\LecturerCredentialBatch;
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
        $plain = Excel::raw(new LecturerLoginCredentialsExport($rows), ExcelWriter::XLSX);
        $key = $this->key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Credential workbook encryption failed.');
        }
        $filename = 'بيانات-دخول-المحاضرين-'.now()->format('Ymd-His').'.xlsx';
        $path = 'lecturer-credentials/'.Str::uuid().'.enc';
        Storage::disk('local')->put($path, $iv.$tag.$cipher);

        return LecturerCredentialBatch::query()->create([
            'academic_term_id' => $term?->id, 'batch_type' => $type, 'original_filename' => $filename,
            'encrypted_file_path' => $path, 'sha256' => hash('sha256', $plain), 'record_count' => count($rows),
            'generated_by' => $actor?->id, 'generated_at' => now(), 'status' => 'available', 'encryption_key_version' => config('services.lecturer_credentials.key_version'),
        ]);
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

    private function key(): string
    {
        $value = (string) config('services.lecturer_credentials.key');
        if (strlen($value) < 32) {
            throw new RuntimeException('Credential encryption key is unavailable.');
        }

        return hash('sha256', $value, true);
    }
}
