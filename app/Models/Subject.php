<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Subject extends Model
{
    use SoftDeletes;

    public const TYPE_THEORETICAL = 'theoretical';

    public const TYPE_PRACTICAL = 'practical';

    public const SEMESTER_FIRST = 'first';

    public const SEMESTER_SECOND = 'second';

    public const SEMESTER_SUMMER = 'summer';

    protected $fillable = [
        'code',
        'name',
        'subject_type',
        'lecturer_id',
        'department_id',
        'credit_hours',
        'level',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $subject): void {
            $subject->subject_type = self::normalizeSubjectType($subject->subject_type);

            if (blank($subject->subject_type)) {
                throw ValidationException::withMessages([
                    'subject_type' => __('subjects.subject_type_required'),
                ]);
            }

            if (! array_key_exists($subject->subject_type, self::subjectTypeOptions())) {
                throw ValidationException::withMessages([
                    'subject_type' => __('subjects.subject_type_invalid'),
                ]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public static function subjectTypeOptions(): array
    {
        return [
            self::TYPE_THEORETICAL => __('subjects.theory'),
            self::TYPE_PRACTICAL => __('subjects.practical'),
        ];
    }

    public static function normalizeSubjectType(mixed $subjectType): ?string
    {
        if (blank($subjectType)) {
            return null;
        }

        return match (mb_strtolower(trim((string) $subjectType))) {
            self::TYPE_THEORETICAL, 'theory', 't', 'نظري' => self::TYPE_THEORETICAL,
            self::TYPE_PRACTICAL, 'practical', 'p', 'عملي' => self::TYPE_PRACTICAL,
            default => trim((string) $subjectType),
        };
    }

    public function getSubjectTypeLabelAttribute(): string
    {
        return self::subjectTypeOptions()[$this->subject_type] ?? __('subjects.not_available');
    }

    public function sectionCodePrefix(): string
    {
        return self::sectionCodePrefixForType($this->subject_type);
    }

    public static function sectionCodePrefixForType(?string $subjectType): string
    {
        return $subjectType === self::TYPE_PRACTICAL ? 'P' : 'T';
    }

    /**
     * @return array<string, string>
     */
    public static function semesterOptions(): array
    {
        return [
            self::SEMESTER_FIRST => __('subjects.semester_first'),
            self::SEMESTER_SECOND => __('subjects.semester_second'),
            self::SEMESTER_SUMMER => __('subjects.semester_summer'),
        ];
    }

    public static function normalizeSemester(mixed $semester): ?string
    {
        if (blank($semester)) {
            return null;
        }

        return match ((string) $semester) {
            '1', self::SEMESTER_FIRST => self::SEMESTER_FIRST,
            '2', self::SEMESTER_SECOND => self::SEMESTER_SECOND,
            '3', self::SEMESTER_SUMMER => self::SEMESTER_SUMMER,
            default => (string) $semester,
        };
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id')->withTrashed();
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function lectureSessions(): HasMany
    {
        return $this->hasMany(LectureSession::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SubjectSection::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'enrollments', 'subject_id', 'student_id')
            ->withTrashed()
            ->withPivot(['semester', 'year', 'status', 'theoretical_section_id', 'practical_section_id', 'registration_date'])
            ->withTimestamps();
    }
}
