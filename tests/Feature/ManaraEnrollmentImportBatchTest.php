<?php

use App\Filament\Pages\ManaraEnrollmentImport;
use App\Models\ImportBatch;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

it('records and reuses one deduplicated enrollment batch with its imported term', function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $user->assignRole('super-admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $path = enrollmentBatchWorkbook();

    try {
        $content = file_get_contents($path);
        $component = Livewire::actingAs($user)->test(ManaraEnrollmentImport::class);
        $component
            ->set('data.file', [UploadedFile::fake()->createWithContent('enrollments.xlsx', $content)])
            ->call('import');

        $batch = ImportBatch::query()->where('import_type', ImportBatch::TYPE_ENROLLMENTS)->firstOrFail();

        expect($batch->status)->toBe(ImportBatch::STATUS_COMPLETED)
            ->and($batch->imported_rows)->toBe(1)
            ->and($batch->academicTerms()->count())->toBe(1)
            ->and($component->get('completedBatchUuid'))->toBe($batch->uuid);

        Livewire::actingAs($user)
            ->test(ManaraEnrollmentImport::class)
            ->set('data.file', [UploadedFile::fake()->createWithContent('enrollments.xlsx', $content)])
            ->call('import');

        expect(ImportBatch::query()->where('import_type', ImportBatch::TYPE_ENROLLMENTS)->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

function enrollmentBatchWorkbook(): string
{
    $path = tempnam(sys_get_temp_dir(), 'enrollment-batch-').'.xlsx';
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['الرقم الجامعي', 'اسم الطالب', 'الكلية', 'الاختصاص', 'اسم المقرر', 'رمز المقرر', 'تاريخ التسجيل', 'الفصل الدراسي', 'رمز الفئة النظرية', 'رمز الفئة العملية'],
        ['2026001', 'طالب دفعة', 'كلية الهندسة', 'هندسة المعلوماتية', 'برمجة', 'BAT101', '01/07/2026', 'الفصل الصيفي 2025/2026', 1, 0],
    ]);
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    return $path;
}
