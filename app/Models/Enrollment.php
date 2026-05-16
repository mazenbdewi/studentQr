<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'theoretical_section_id',
        'practical_section_id',
        'registration_date',
        'semester',
        'year',
        'status',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'subject_id' => 'integer',
        'theoretical_section_id' => 'integer',
        'practical_section_id' => 'integer',
        'registration_date' => 'date',
        'year' => 'integer',
    ];

    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_DROPPED = 'dropped';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ENROLLED => __('enrollments.enrolled'),
            self::STATUS_DROPPED => __('enrollments.dropped'),
            self::STATUS_PASSED => __('enrollments.passed'),
            self::STATUS_FAILED => __('enrollments.failed'),
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function theoreticalSection(): BelongsTo
    {
        return $this->belongsTo(SubjectSection::class, 'theoretical_section_id');
    }

    public function practicalSection(): BelongsTo
    {
        return $this->belongsTo(SubjectSection::class, 'practical_section_id');
    }
}
