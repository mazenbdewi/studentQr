<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hall extends Model
{
    use SoftDeletes;

    public const TYPE_LECTURE_HALL = 'lecture_hall';

    public const TYPE_LABORATORY = 'laboratory';

    public const TYPE_WORKSHOP = 'workshop';

    public const TYPE_COMPUTER_LAB = 'computer_lab';

    public const TYPE_DRAWING_STUDIO = 'drawing_studio';

    public const TYPE_AMPHITHEATRE = 'amphitheatre';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'code',
        'name',
        'capacity',
        'hall_type',
        'building_name',
        'faculty_id',
        'floor',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'faculty_id' => 'integer',
        'floor' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return array<string, string> */
    public static function hallTypeOptions(): array
    {
        return [
            self::TYPE_LECTURE_HALL => 'قاعة نظرية',
            self::TYPE_LABORATORY => 'مخبر',
            self::TYPE_WORKSHOP => 'ورشة',
            self::TYPE_COMPUTER_LAB => 'مخبر حاسوبي',
            self::TYPE_DRAWING_STUDIO => 'مرسم',
            self::TYPE_AMPHITHEATRE => 'مدرج',
            self::TYPE_OTHER => 'أخرى',
        ];
    }

    /** @return array<int, string> */
    public static function workshopCompatibleTypes(): array
    {
        return [
            self::TYPE_WORKSHOP,
        ];
    }

    public static function normalizeHallType(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = trim((string) $value);
        $lower = mb_strtolower($value);

        return match ($lower) {
            self::TYPE_LECTURE_HALL, 'theoretical', 'lecture', 'قاعة نظرية', 'نظري' => self::TYPE_LECTURE_HALL,
            self::TYPE_LABORATORY, 'lab', 'مخبر', 'مختبر' => self::TYPE_LABORATORY,
            self::TYPE_WORKSHOP, 'ورشة', 'ورشه' => self::TYPE_WORKSHOP,
            self::TYPE_COMPUTER_LAB, 'computer lab', 'computer_lab', 'مخبر حاسوبي', 'مختبر حاسوبي' => self::TYPE_COMPUTER_LAB,
            self::TYPE_DRAWING_STUDIO, 'studio', 'drawing studio', 'مرسم' => self::TYPE_DRAWING_STUDIO,
            self::TYPE_AMPHITHEATRE, 'amphitheater', 'مدرج' => self::TYPE_AMPHITHEATRE,
            self::TYPE_OTHER, 'other', 'أخرى', 'اخرى' => self::TYPE_OTHER,
            default => $value,
        };
    }

    public function displayLabel(): string
    {
        $parts = collect([$this->code, $this->name])
            ->filter(fn (mixed $value): bool => filled(trim((string) $value)))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->values();

        return $parts->isEmpty() ? 'بدون اسم' : $parts->implode(' — ');
    }

    public function getHallTypeLabelAttribute(): string
    {
        return self::hallTypeOptions()[$this->hall_type] ?? 'غير محدد';
    }

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class)->withTrashed();
    }
}
