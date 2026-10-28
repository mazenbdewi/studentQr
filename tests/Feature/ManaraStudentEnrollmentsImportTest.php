<?php

use App\Filament\Pages\ManaraEnrollmentImport;
use App\Imports\ManaraStudentEnrollmentsImport;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use App\Support\XlsxNumericCellSanitizer;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
});

it('imports Manara enrollment rows with theoretical and practical sections idempotently', function (): void {
    $path = manaraWorkbookPath([
        manaraRow('2026001', 'أحمد علي', 'كلية الهندسة', 'هندسة العمارة', 'تصميم معماري', 'ARC101', '01/04/2026', 1, null),
        manaraRow('2026002', 'ليلى محمود', 'كلية الهندسة', 'هندسة العمارة', 'تصميم معماري', 'ARC101', '01/04/2026', null, 1),
        manaraRow('2026003', 'سامر حسن', 'كلية الهندسة', 'هندسة العمارة', 'تصميم معماري', 'ARC101', '01/04/2026', 2, 2, 3),
    ]);

    try {
        $import = new ManaraStudentEnrollmentsImport;
        Excel::import($import, $path);

        expect($import->getSummary())->toMatchArray([
            'total_rows' => 3,
            'imported_rows' => 3,
            'skipped_rows' => 0,
            'created_students' => 3,
            'created_terms' => 1,
            'created_colleges' => 1,
            'created_specializations' => 1,
            'created_subjects' => 1,
            'created_theoretical_sections' => 2,
            'created_practical_sections' => 2,
            'created_enrollments' => 3,
            'failed_rows' => 0,
        ]);

        $subject = Subject::query()->where('code', 'ARC101')->firstOrFail();
        $academicTerm = AcademicTerm::query()->where('canonical_name', 'الفصل الصيفي 2025/2026')->firstOrFail();

        expect($subject->sections()->orderBy('code')->pluck('section_type', 'code')->all())->toBe([
            'P1' => Subject::TYPE_PRACTICAL,
            'P2' => Subject::TYPE_PRACTICAL,
            'T1' => Subject::TYPE_THEORETICAL,
            'T2' => Subject::TYPE_THEORETICAL,
        ]);

        $theoryOnly = Enrollment::query()
            ->whereBelongsTo(Student::query()->where('student_number', '2026001')->firstOrFail())
            ->firstOrFail();
        $practicalOnly = Enrollment::query()
            ->whereBelongsTo(Student::query()->where('student_number', '2026002')->firstOrFail())
            ->firstOrFail();
        $both = Enrollment::query()
            ->whereBelongsTo(Student::query()->where('student_number', '2026003')->firstOrFail())
            ->firstOrFail();

        expect($theoryOnly->theoreticalSection?->code)->toBe('T1')
            ->and($theoryOnly->theoreticalSection?->code)->toBeString()
            ->and($theoryOnly->theoreticalSection?->section_number)->toBe(1)
            ->and($theoryOnly->practical_section_id)->toBeNull()
            ->and($practicalOnly->theoretical_section_id)->toBeNull()
            ->and($practicalOnly->practicalSection?->code)->toBe('P1')
            ->and($practicalOnly->practicalSection?->code)->toBeString()
            ->and($practicalOnly->practicalSection?->section_number)->toBe(1)
            ->and($both->theoreticalSection?->code)->toBe('T2')
            ->and($both->practicalSection?->code)->toBe('P2')
            ->and($both->registration_date?->toDateString())->toBe('2026-04-01')
            ->and($both->semester)->toBeNull()
            ->and($both->academic_term_id)->toBe($academicTerm->id)
            ->and($both->year)->toBe(3);

        Excel::import(new ManaraStudentEnrollmentsImport, $path);

        expect(Student::query()->count())->toBe(3)
            ->and(Subject::query()->count())->toBe(1)
            ->and(SubjectSection::query()->count())->toBe(4)
            ->and(SubjectSection::query()->where('academic_term_id', $academicTerm->id)->count())->toBe(4)
            ->and(Enrollment::query()->count())->toBe(3);
    } finally {
        @unlink($path);
    }
});

it('accepts a new null section row without creating an enrollment or an error', function (): void {
    $path = manaraWorkbookPath([
        manaraRow('2026004', 'طالب بلا شعبة', 'كلية الهندسة', 'هندسة العمارة', 'إنشاءات', 'ARC202', '01/04/2026', null, null),
    ]);

    try {
        $import = new ManaraStudentEnrollmentsImport;
        Excel::import($import, $path);

        expect($import->getSummary())->toMatchArray([
            'total_rows' => 1,
            'imported_rows' => 1,
            'skipped_rows' => 0,
            'skipped_no_section' => 1,
            'removed_enrollments' => 0,
            'failed_rows' => 0,
        ])
            ->and($import->getErrors())->toBeEmpty()
            ->and(Student::query()->count())->toBe(1)
            ->and(Subject::query()->count())->toBe(1)
            ->and(Enrollment::query()->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('treats zero and whitespace sections as empty without creating T0 or P0', function (): void {
    $path = manaraWorkbookPath([
        manaraRow('2026010', 'طالب عملي', 'كلية الهندسة', 'هندسة المعلوماتية', 'مخبر', 'LAB101', '01/04/2026', 0, 1),
        manaraRow('2026011', 'طالب بلا شعبة', 'كلية الهندسة', 'هندسة المعلوماتية', 'مخبر', 'LAB101', '01/04/2026', '0.0', '0'),
        manaraRow('2026012', 'طالب فراغ', 'كلية الهندسة', 'هندسة المعلوماتية', 'مخبر', 'LAB101', '01/04/2026', '   ', ''),
    ]);

    try {
        $import = new ManaraStudentEnrollmentsImport;
        Excel::import($import, $path);

        $enrollment = Enrollment::query()->firstOrFail();

        expect($import->getSummary())->toMatchArray([
            'total_rows' => 3,
            'imported_rows' => 3,
            'skipped_no_section' => 2,
            'failed_rows' => 0,
        ])
            ->and($import->getErrors())->toBeEmpty()
            ->and($enrollment->theoretical_section_id)->toBeNull()
            ->and($enrollment->practicalSection?->code)->toBe('P1')
            ->and(SubjectSection::query()->whereIn('code', ['T0', 'P0'])->exists())->toBeFalse();
    } finally {
        @unlink($path);
    }
});

it('removes only the exact term student subject enrollment and can recreate it later', function (): void {
    $initialPath = manaraWorkbookPath([
        manaraRow('2026100', 'الطالب أ', 'كلية الهندسة', 'هندسة المعلوماتية', 'برمجة', 'PRG301', '01/04/2026', 1, 1, academicTerm: 'الفصل الأول 2026/2027'),
        manaraRow('2026100', 'الطالب أ', 'كلية الهندسة', 'هندسة المعلوماتية', 'قواعد بيانات', 'DB301', '01/04/2026', 1, 0, academicTerm: 'الفصل الأول 2026/2027'),
        manaraRow('2026100', 'الطالب أ', 'كلية الهندسة', 'هندسة المعلوماتية', 'برمجة', 'PRG301', '01/07/2026', 2, 0, academicTerm: 'الفصل الصيفي 2026/2027'),
        manaraRow('2026200', 'الطالب ب', 'كلية الهندسة', 'هندسة المعلوماتية', 'برمجة', 'PRG301', '01/04/2026', 1, 0, academicTerm: 'الفصل الأول 2026/2027'),
    ]);
    $removePath = manaraWorkbookPath([
        manaraRow('2026100', 'الطالب أ', 'كلية الهندسة', 'هندسة المعلوماتية', 'برمجة', 'PRG301', '02/04/2026', 0, 0, academicTerm: 'الفصل الأول 2026/2027'),
    ]);
    $recreatePath = manaraWorkbookPath([
        manaraRow('2026100', 'الطالب أ', 'كلية الهندسة', 'هندسة المعلوماتية', 'برمجة', 'PRG301', '03/04/2026', 2, 0, academicTerm: 'الفصل الأول 2026/2027'),
    ]);
    $user = User::factory()->create(['login_username' => '2026100']);

    try {
        Excel::import(new ManaraStudentEnrollmentsImport, $initialPath);
        $student = Student::query()->where('student_number', '2026100')->firstOrFail();
        $subject = Subject::query()->where('code', 'PRG301')->firstOrFail();
        $term = AcademicTerm::query()->where('canonical_name', 'الفصل الأول 2026/2027')->firstOrFail();

        $import = new ManaraStudentEnrollmentsImport;
        Excel::import($import, $removePath);

        expect($import->getSummary())->toMatchArray([
            'imported_rows' => 1,
            'removed_enrollments' => 1,
            'skipped_no_section' => 0,
            'failed_rows' => 0,
        ])->and($import->getErrors())->toBeEmpty()
            ->and(Enrollment::query()->whereBelongsTo($student)->whereBelongsTo($subject)->where('academic_term_id', $term->id)->exists())->toBeFalse()
            ->and(Enrollment::query()->whereBelongsTo($student)->count())->toBe(2)
            ->and(Enrollment::query()->where('student_id', '!=', $student->id)->count())->toBe(1)
            ->and(Student::query()->whereKey($student->id)->exists())->toBeTrue()
            ->and(User::query()->whereKey($user->id)->exists())->toBeTrue()
            ->and(Subject::query()->whereKey($subject->id)->exists())->toBeTrue();

        Excel::import(new ManaraStudentEnrollmentsImport, $recreatePath);

        $recreated = Enrollment::query()
            ->whereBelongsTo($student)
            ->whereBelongsTo($subject)
            ->where('academic_term_id', $term->id)
            ->firstOrFail();

        expect($recreated->theoreticalSection?->code)->toBe('T2')
            ->and($recreated->practical_section_id)->toBeNull();
    } finally {
        @unlink($initialPath);
        @unlink($removePath);
        @unlink($recreatePath);
    }
});

it('merges separate theoretical and practical rows for the same student subject and term', function (): void {
    $path = manaraWorkbookPath([
        manaraRow('2026012', 'طالب شعبتين', 'كلية الهندسة', 'هندسة المعلوماتية', 'برمجة', 'PRG201', '01/04/2026', 1, 0),
        manaraRow('2026012', 'طالب شعبتين', 'كلية الهندسة', 'هندسة المعلوماتية', 'برمجة', 'PRG201', '01/04/2026', 0, 1),
    ]);

    try {
        Excel::import(new ManaraStudentEnrollmentsImport, $path);
        $enrollment = Enrollment::query()->firstOrFail();

        expect(Enrollment::query()->count())->toBe(1)
            ->and($enrollment->theoreticalSection?->code)->toBe('T1')
            ->and($enrollment->practicalSection?->code)->toBe('P1');
    } finally {
        @unlink($path);
    }
});

it('keeps enrollments and section codes separate across academic terms', function (): void {
    $path = manaraWorkbookPath([
        manaraRow('2026013', 'طالب فصلين', 'كلية الهندسة', 'هندسة المعلوماتية', 'خوارزميات', 'ALG101', '01/04/2026', 1, null, academicTerm: 'الفصل الأول 2025/2026'),
        manaraRow('2026013', 'طالب فصلين', 'كلية الهندسة', 'هندسة المعلوماتية', 'خوارزميات', 'ALG101', '01/07/2026', 1, null, academicTerm: 'الفصل الصيفي 2025/2026'),
    ]);

    try {
        Excel::import(new ManaraStudentEnrollmentsImport, $path);

        expect(AcademicTerm::query()->count())->toBe(2)
            ->and(Enrollment::query()->count())->toBe(2)
            ->and(SubjectSection::query()->where('code', 'T1')->count())->toBe(2)
            ->and(Subject::query()->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('blocks the apply importer when the academic term heading is missing', function (): void {
    $path = manaraWorkbookPath([
        manaraRow('2026014', 'طالب بلا فصل', 'كلية الهندسة', 'هندسة المعلوماتية', 'شبكات', 'NET101', '01/04/2026', 1, null),
    ], includeAcademicTermHeading: false);

    try {
        expect(fn () => Excel::import(new ManaraStudentEnrollmentsImport, $path))
            ->toThrow(RuntimeException::class);

        expect(AcademicTerm::query()->count())->toBe(0)
            ->and(Student::query()->count())->toBe(0)
            ->and(Enrollment::query()->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('imports another Manara file without deleting previous records', function (): void {
    $firstPath = manaraWorkbookPath([
        manaraRow('2026005', 'طالب قديم', 'كلية الهندسة', 'هندسة مدنية', 'رياضيات', 'MTH101', '01/04/2026', 1, null),
    ]);
    $secondPath = manaraWorkbookPath([
        manaraRow('2026006', 'طالب جديد', 'كلية الهندسة', 'هندسة مدنية', 'فيزياء', 'PHY101', '02/04/2026', null, 1),
    ]);

    try {
        Excel::import(new ManaraStudentEnrollmentsImport, $firstPath);
        Excel::import(new ManaraStudentEnrollmentsImport, $secondPath);

        expect(Student::query()->where('student_number', '2026005')->exists())->toBeTrue()
            ->and(Student::query()->where('student_number', '2026006')->exists())->toBeTrue()
            ->and(Subject::query()->where('code', 'MTH101')->exists())->toBeTrue()
            ->and(Subject::query()->where('code', 'PHY101')->exists())->toBeTrue()
            ->and(Enrollment::query()->count())->toBe(2);
    } finally {
        @unlink($firstPath);
        @unlink($secondPath);
    }
});

it('normalizes decimal and prefixed section codes without numeric storage', function (): void {
    $path = manaraWorkbookPath([
        manaraRow('2026007', 'طالبة شعبة نصية', 'كلية الهندسة', 'هندسة طبية', 'كيمياء', 'CHEM101', '01/04/2026', 'T1', 'P1'),
        manaraRow('2026008', 'طالب شعبة عشرية', 'كلية الهندسة', 'هندسة طبية', 'كيمياء', 'CHEM101', '01/04/2026', '2.0', '2.0'),
    ]);

    try {
        Excel::import(new ManaraStudentEnrollmentsImport, $path);

        $subject = Subject::query()->where('code', 'CHEM101')->firstOrFail();

        expect($subject->sections()->orderBy('code')->pluck('code')->all())->toBe(['P1', 'P2', 'T1', 'T2'])
            ->and($subject->sections()->where('code', 'T1')->first()?->code)->toBeString()
            ->and($subject->sections()->where('code', 'P1')->first()?->code)->toBeString()
            ->and($subject->sections()->where('code', 'T2')->first()?->section_number)->toBe(2)
            ->and($subject->sections()->where('code', 'P2')->first()?->section_number)->toBe(2);
    } finally {
        @unlink($path);
    }
});

it('does not require academic year or semester on the Manara import page', function (): void {
    $user = User::factory()->create([
        'login_username' => 'manara_admin',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $user->assignRole('super-admin');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($user)
        ->test(ManaraEnrollmentImport::class)
        ->call('import')
        ->assertHasFormErrors(['file'])
        ->assertHasNoFormErrors(['semester', 'year'])
        ->assertSee(__('manara-import.upload_loading'))
        ->assertSee(__('manara-import.import_loading'));
});

it('tracks a valid upload through removal and replacement without invoking import', function (): void {
    $path = manaraWorkbookPath([
        manaraRow('2026999', 'طالب رفع', 'كلية الهندسة', 'هندسة المعلوماتية', 'برمجة', 'UPL101', '01/07/2026', 1, null),
    ]);
    $user = User::factory()->create([
        'login_username' => 'manara_upload_admin',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $user->assignRole('super-admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    try {
        $component = Livewire::actingAs($user)->test(ManaraEnrollmentImport::class);

        expect($component->get('uploadReady'))->toBeFalse();

        $component
            ->set('data.file', [UploadedFile::fake()->createWithContent('enrollments.xlsx', file_get_contents($path))])
            ->call('verifyUploadedFileReady');

        expect($component->get('uploadReady'))->toBeTrue()
            ->and($component->get('data.file'))->toBeArray()->not->toBeEmpty();

        $component
            ->set('data.file', [])
            ->call('verifyUploadedFileReady');

        expect($component->get('uploadReady'))->toBeFalse();

        $component
            ->set('data.file', [UploadedFile::fake()->createWithContent('replacement.xlsx', file_get_contents($path))])
            ->call('verifyUploadedFileReady');

        expect($component->get('uploadReady'))->toBeTrue();
    } finally {
        @unlink($path);
    }
});

it('repairs xlsx cells marked numeric while containing section text before import', function (): void {
    $path = manaraWorkbookPath([
        manaraRow('2026009', 'طالب ملف غير سليم', 'كلية الهندسة', 'هندسة طبية', 'تشريح', 'BIO101', '01/04/2026', 'T1', 'P1'),
    ]);

    makeManaraSectionCellsInvalidNumeric($path, ['J2' => 'T1', 'K2' => 'P1']);

    try {
        $sanitizedPath = app(XlsxNumericCellSanitizer::class)->sanitizeToTemporaryFile($path);

        try {
            Excel::import(new ManaraStudentEnrollmentsImport, $sanitizedPath);
        } finally {
            app(XlsxNumericCellSanitizer::class)->deleteTemporaryFile($sanitizedPath);
        }

        $subject = Subject::query()->where('code', 'BIO101')->firstOrFail();

        expect($subject->sections()->orderBy('code')->pluck('code')->all())->toBe(['P1', 'T1']);
    } finally {
        @unlink($path);
    }
});

it('downloads Manara import error files through the admin route', function (): void {
    $user = User::factory()->create([
        'login_username' => 'manara_errors_admin',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $user->assignRole('super-admin');

    Storage::fake('public');

    $fileName = 'manara-enrollment-errors-20260517-012650.xlsx';
    Storage::disk('public')->put("import-errors/{$fileName}", 'error file content');

    $this->actingAs($user)
        ->get(route('admin.manara-enrollment-import.errors.download', ['fileName' => $fileName], false))
        ->assertOk()
        ->assertDownload($fileName);
});

function manaraWorkbookPath(array $rows, bool $includeAcademicTermHeading = true): string
{
    $headings = [
        'م',
        'الرقم الجامعي',
        'اسم الطالب',
        'الكلية',
        'الاختصاص',
        'اسم المقرر',
        'رمز المقرر',
        'تاريخ التسجيل',
        'الفصل الدراسي',
        'رمز الفئة النظرية',
        'رمز الفئة العملية',
        'عبئ المقرر',
        'حالة التسجيل',
        'مستوى المقرر',
        'سعة الفئة النظرية',
    ];

    if (! $includeAcademicTermHeading) {
        $termIndex = array_search('الفصل الدراسي', $headings, true);
        unset($headings[$termIndex]);
        $headings = array_values($headings);
        $rows = array_map(function (array $row) use ($termIndex): array {
            unset($row[$termIndex]);

            return array_values($row);
        }, $rows);
    }

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($headings, null, 'A1');

    foreach (array_values($rows) as $index => $row) {
        $sheet->fromArray($row, null, 'A'.($index + 2));
    }

    $path = sys_get_temp_dir().'/manara_import_'.Str::uuid().'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function manaraRow(
    string $studentNumber,
    string $studentName,
    string $faculty,
    string $department,
    string $subjectName,
    string $subjectCode,
    string $registrationDate,
    mixed $theoreticalSection,
    mixed $practicalSection,
    mixed $courseLevel = null,
    mixed $academicTerm = 'الفصل الصيفي 2025/2026',
): array {
    return [
        null,
        $studentNumber,
        $studentName,
        $faculty,
        $department,
        $subjectName,
        $subjectCode,
        $registrationDate,
        $academicTerm,
        $theoreticalSection,
        $practicalSection,
        null,
        null,
        $courseLevel,
        null,
    ];
}

function makeManaraSectionCellsInvalidNumeric(string $path, array $cells): void
{
    $zip = new \ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    expect($sheetXml)->toBeString();

    $document = new \DOMDocument;
    $document->loadXML($sheetXml);

    foreach ($document->getElementsByTagName('c') as $cell) {
        $reference = $cell->getAttribute('r');

        if (! array_key_exists($reference, $cells)) {
            continue;
        }

        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }

        $cell->setAttribute('t', 'n');
        $value = $document->createElement('v');
        $value->appendChild($document->createTextNode($cells[$reference]));
        $cell->appendChild($value);
    }

    $zip->addFromString('xl/worksheets/sheet1.xml', $document->saveXML());
    $zip->close();
}
