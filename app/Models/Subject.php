<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use SoftDeletes;

    public const SEMESTER_FIRST = 'first';
    public const SEMESTER_SECOND = 'second';
    public const SEMESTER_SUMMER = 'summer';

    protected $fillable = [
        'code',
        'name',
        'lecturer_id',
        'department_id',
        'credit_hours',
        'level',
        'semester',
        'is_active',
    ];

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

    public function getSemesterLabelAttribute(): string
    {
        $semester = self::normalizeSemester($this->semester);

        return $semester && array_key_exists($semester, self::semesterOptions())
            ? self::semesterOptions()[$semester]
            : __('subjects.not_available');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id')->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function lectureSessions(): HasMany
    {
        return $this->hasMany(LectureSession::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'enrollments', 'subject_id', 'student_id')
            ->withTrashed()
            ->withPivot(['semester', 'year', 'status'])
            ->withTimestamps();
    }
}
