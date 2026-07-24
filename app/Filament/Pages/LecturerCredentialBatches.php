<?php

namespace App\Filament\Pages;

use App\Models\LecturerCredentialBatch;
use App\Services\LecturerCredentialBatchService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LecturerCredentialBatches extends Page
{
    public ?int $detailsBatchId = null;

    protected static ?string $slug = 'lecturer-credential-batches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    protected string $view = 'filament.pages.lecturer-credential-batches';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user?->hasRole('super-admin') || $user?->can('view lecturer credential batches');
    }

    public static function getNavigationLabel(): string
    {
        return 'دفعات بيانات دخول المحاضرين';
    }

    public function getTitle(): string
    {
        return 'دفعات بيانات دخول المحاضرين';
    }

    public function batches()
    {
        return LecturerCredentialBatch::query()->with(['academicTerm', 'generatedBy'])->latest('generated_at')->get();
    }

    public function openDetails(int $id): void
    {
        $this->detailsBatchId = LecturerCredentialBatch::query()->findOrFail($id)->id;
    }

    public function closeDetails(): void
    {
        $this->detailsBatchId = null;
    }

    public function detailsBatch(): ?LecturerCredentialBatch
    {
        return $this->detailsBatchId
            ? LecturerCredentialBatch::query()->with(['academicTerm', 'generatedBy'])->find($this->detailsBatchId)
            : null;
    }

    public function batchTypeLabel(string $type): string
    {
        return match ($type) {
            'password_reset' => 'إعادة ضبط كلمات المرور',
            'account_creation', 'initial_accounts' => 'إنشاء حسابات جديدة',
            default => 'دفعة بيانات دخول',
        };
    }

    public function batchStatusLabel(string $status): string
    {
        return match ($status) {
            'available' => 'متاحة للتنزيل',
            'deleted' => 'محذوفة بأمان',
            'failed' => 'فشلت',
            default => 'غير متاحة',
        };
    }

    public function download(int $id, LecturerCredentialBatchService $service): StreamedResponse
    {
        $user = Filament::auth()->user();
        abort_unless($user?->hasRole('super-admin') || $user?->can('download lecturer credential batches'), 403);
        $batch = LecturerCredentialBatch::query()->findOrFail($id);
        try {
            if ($batch->status === 'deleted') {
                throw new \RuntimeException('Credential batch is deleted.');
            }

            $contents = $service->decryptedContents($batch);
            $service->recordDownload($batch);
            $service->audit($batch, 'download_prepared', $user);

            return response()->streamDownload(fn () => print ($contents), $batch->original_filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        } catch (\Throwable) {
            $service->audit($batch, 'download_failed', $user);
            abort(422, 'تعذر تحضير ملف بيانات الدخول بأمان.');
        }
    }

    public function secureDelete(int $id, LecturerCredentialBatchService $service): void
    {
        $user = Filament::auth()->user();
        abort_unless($user?->hasRole('super-admin') || $user?->can('delete lecturer credential batches'), 403);
        $batch = LecturerCredentialBatch::query()->findOrFail($id);
        try {
            $service->delete($batch, $user);
        } catch (\Throwable) {
            $service->audit($batch, 'delete_failed', $user);
            abort(422, 'تعذر حذف ملف بيانات الدخول بأمان.');
        }
    }
}
