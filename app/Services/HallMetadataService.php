<?php

namespace App\Services;

use App\Models\Faculty;
use App\Models\Hall;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class HallMetadataService
{
    public const TEMPLATE_FILENAME = 'hall-metadata-template.xlsx';

    public const WORKSHEET_HALLS = 'بيانات القاعات';

    public const WORKSHEET_SUCCESS = 'العمليات الناجحة';

    public const WORKSHEET_ERRORS = 'الأخطاء والحالات المستبعدة';

    /** @return array<int, string> */
    public static function headings(): array
    {
        return [
            'رمز القاعة',
            'اسم القاعة',
            'السعة',
            'نوع القاعة',
            'المبنى',
            'الكلية',
            'الطابق',
            'فعالة',
            'ملاحظات',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function templateRows(): array
    {
        return Hall::query()
            ->with('faculty')
            ->withoutTrashed()
            ->orderBy('code')
            ->get()
            ->map(fn (Hall $hall): array => [
                'رمز القاعة' => $hall->code,
                'اسم القاعة' => $hall->name,
                'السعة' => $this->attributeIfSupported($hall, 'capacity'),
                'نوع القاعة' => $hall->hall_type ? (Hall::hallTypeOptions()[$hall->hall_type] ?? $hall->hall_type) : '',
                'المبنى' => $this->attributeIfSupported($hall, 'building_name'),
                'الكلية' => $this->attributeIfSupported($hall, 'faculty_id') ? $this->relatedString($hall, 'faculty', 'name') : '',
                'الطابق' => $hall->floor,
                'فعالة' => $hall->is_active ? 'نعم' : 'لا',
                'ملاحظات' => $this->attributeIfSupported($hall, 'notes'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{success_rows: array<int, array<string, mixed>>, error_rows: array<int, array<string, mixed>>, writes_performed: bool}
     */
    public function preview(array $rows, bool $allowCreate = false): array
    {
        return $this->process($rows, $allowCreate, write: false);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{success_rows: array<int, array<string, mixed>>, error_rows: array<int, array<string, mixed>>, writes_performed: bool}
     */
    public function import(array $rows, bool $allowCreate = false): array
    {
        return $this->process($rows, $allowCreate, write: true);
    }

    /** @return array<int, array<string, mixed>> */
    public function rowsFromSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        try {
            $sheet = $spreadsheet->getSheetByName(self::WORKSHEET_HALLS) ?? $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            $headerRow = array_shift($rows) ?: [];
            $headings = collect($headerRow)
                ->map(fn (mixed $heading): string => trim((string) $heading))
                ->filter()
                ->all();

            return collect($rows)
                ->map(function (array $row) use ($headings): array {
                    $mapped = [];
                    foreach ($headings as $column => $heading) {
                        $mapped[$heading] = $row[$column] ?? null;
                    }

                    return $mapped;
                })
                ->filter(fn (array $row): bool => collect($row)->filter(fn (mixed $value): bool => filled($value))->isNotEmpty())
                ->values()
                ->all();
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{success_rows: array<int, array<string, mixed>>, error_rows: array<int, array<string, mixed>>, writes_performed: bool}
     */
    private function process(array $rows, bool $allowCreate, bool $write): array
    {
        $successRows = [];
        $errorRows = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $code = trim((string) ($row['رمز القاعة'] ?? $row['code'] ?? ''));
            $name = trim((string) ($row['اسم القاعة'] ?? $row['name'] ?? ''));

            if ($code === '') {
                $errorRows[] = $this->errorRow($line, $code, 'missing_code', 'رمز القاعة مطلوب.', 'أدخل رمز قاعة موجوداً كما هو في النظام.');

                continue;
            }

            $hall = Hall::query()->withoutTrashed()->where('code', $code)->first();

            if (! $hall instanceof Hall && ! $allowCreate) {
                $errorRows[] = $this->errorRow($line, $code, 'hall_not_found', 'رمز القاعة غير موجود، ولا يسمح هذا المسار بإنشاء قاعات جديدة.', 'راجع الرمز أو فعّل مسار إنشاء القاعات صراحة.');

                continue;
            }

            $changes = [];
            $validationErrors = [];

            if ($name !== '') {
                $changes['name'] = $name;
            }

            if ($this->rowHasFilled($row, 'السعة', 'capacity')) {
                $capacity = (int) $this->rowValue($row, 'السعة', 'capacity');
                if ($capacity < 1) {
                    $validationErrors[] = ['invalid_capacity', 'السعة يجب أن تكون رقماً صحيحاً موجباً.', 'أدخل رقماً أكبر من صفر أو اترك الخانة فارغة للحفاظ على القيمة الحالية.'];
                } elseif (Schema::hasColumn('halls', 'capacity')) {
                    $changes['capacity'] = $capacity;
                }
            }

            if ($this->rowHasFilled($row, 'نوع القاعة', 'hall_type')) {
                $type = Hall::normalizeHallType($this->rowValue($row, 'نوع القاعة', 'hall_type'));
                if (! array_key_exists((string) $type, Hall::hallTypeOptions())) {
                    $validationErrors[] = ['invalid_hall_type', 'نوع القاعة غير مدعوم.', 'استخدم إحدى القيم المعتمدة: '.implode('، ', Hall::hallTypeOptions()).'.'];
                } elseif (Schema::hasColumn('halls', 'hall_type')) {
                    $changes['hall_type'] = $type;
                }
            }

            if ($this->rowHasFilled($row, 'المبنى', 'building_name') && Schema::hasColumn('halls', 'building_name')) {
                $changes['building_name'] = trim((string) $this->rowValue($row, 'المبنى', 'building_name'));
            }

            if ($this->rowHasFilled($row, 'الكلية', 'faculty') && Schema::hasColumn('halls', 'faculty_id')) {
                $facultyName = trim((string) $this->rowValue($row, 'الكلية', 'faculty'));
                $facultyId = Faculty::query()->where('name', $facultyName)->value('id');
                if (! $facultyId) {
                    $validationErrors[] = ['faculty_not_found', 'الكلية غير موجودة بالاسم المدخل.', 'اترك الخانة فارغة أو أدخل اسم كلية موجوداً كما هو.'];
                } else {
                    $changes['faculty_id'] = $facultyId;
                }
            }

            if ($this->rowHasFilled($row, 'الطابق', 'floor')) {
                $changes['floor'] = (int) $this->rowValue($row, 'الطابق', 'floor');
            }

            if ($this->rowHasFilled($row, 'فعالة', 'is_active') && Schema::hasColumn('halls', 'is_active')) {
                $active = $this->parseBoolean($this->rowValue($row, 'فعالة', 'is_active'));
                if ($active === null) {
                    $validationErrors[] = ['invalid_active_flag', 'حالة الفعالية غير مفهومة.', 'استخدم نعم/لا أو true/false أو 1/0.'];
                } else {
                    $changes['is_active'] = $active;
                }
            }

            if ($this->rowHasFilled($row, 'ملاحظات', 'notes') && Schema::hasColumn('halls', 'notes')) {
                $changes['notes'] = trim((string) $this->rowValue($row, 'ملاحظات', 'notes'));
            }

            if ($validationErrors !== []) {
                foreach ($validationErrors as [$codeValue, $reason, $suggestedAction]) {
                    $errorRows[] = $this->errorRow($line, $code, $codeValue, $reason, $suggestedAction);
                }

                continue;
            }

            if ($write) {
                DB::transaction(function () use (&$hall, $code, $changes, $allowCreate): void {
                    if (! $hall instanceof Hall && $allowCreate) {
                        $hall = Hall::query()->create([
                            'code' => $code,
                            'name' => $changes['name'] ?? $code,
                            'floor' => $changes['floor'] ?? null,
                            'is_active' => $changes['is_active'] ?? true,
                        ]);
                    }

                    if ($hall instanceof Hall && $changes !== []) {
                        $hall->fill($changes)->save();
                    }
                });
            }

            $successRows[] = [
                'رقم الصف' => $line,
                'رمز القاعة' => $code,
                'اسم القاعة' => $changes['name'] ?? ($hall instanceof Hall ? $hall->name : $name),
                'النتيجة' => $hall instanceof Hall ? ($changes === [] ? 'لا تغيير' : 'metadata_updated') : 'hall_would_be_created',
                'الملاحظة' => $changes === [] ? 'لا توجد قيم جديدة؛ الخلايا الفارغة تحافظ على البيانات الحالية.' : 'القيم صالحة للمعاينة أو الاستيراد.',
            ];
        }

        return [
            'success_rows' => $successRows,
            'error_rows' => $errorRows,
            'writes_performed' => $write,
        ];
    }

    /** @return array<string, mixed> */
    private function errorRow(int $line, string $hallCode, string $errorCode, string $reason, string $suggestedAction): array
    {
        return [
            'رقم الصف' => $line,
            'رمز القاعة' => $hallCode,
            'رمز الخطأ' => $errorCode,
            'السبب بالعربية' => $reason,
            'الإجراء المقترح' => $suggestedAction,
        ];
    }

    private function rowHasFilled(array $row, string $arabic, string $english): bool
    {
        return filled($this->rowValue($row, $arabic, $english));
    }

    private function rowValue(array $row, string $arabic, string $english): mixed
    {
        return $row[$arabic] ?? $row[$english] ?? null;
    }

    private function parseBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match (mb_strtolower(trim((string) $value))) {
            '1', 'true', 'yes', 'y', 'نعم', 'فعالة', 'نشط', 'active' => true,
            '0', 'false', 'no', 'n', 'لا', 'غير فعالة', 'غير نشط', 'inactive' => false,
            default => null,
        };
    }

    private function attributeIfSupported(Hall $hall, string $attribute): mixed
    {
        if (! Schema::hasColumn('halls', $attribute)) {
            return '';
        }

        return $hall->getAttribute($attribute) ?? '';
    }

    private function relatedString(Model $model, string $relation, string $attribute): string
    {
        $related = $model->getRelationValue($relation);

        return $related instanceof Model ? (string) ($related->getAttribute($attribute) ?? '') : '';
    }
}
