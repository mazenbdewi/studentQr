<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectSectionScheduleSlot extends Model
{
    protected $fillable = [
        'import_batch_id',
        'academic_term_id',
        'subject_id',
        'subject_section_id',
        'lecturer_id',
        'hall_id',
        'weekday',
        'start_time',
        'end_time',
        'section_capacity',
        'expected_student_count',
        'raw_teacher_name',
        'raw_hall_name',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'section_capacity' => 'integer',
        'expected_student_count' => 'integer',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function subjectSection(): BelongsTo
    {
        return $this->belongsTo(SubjectSection::class);
    }

    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    /** @return BelongsTo<Hall, $this> */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class)->withTrashed();
    }
}
