<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleImportRowTimeOverride extends Model
{
    protected $fillable = [
        'schedule_import_row_id',
        'weekday',
        'start_time',
        'end_time',
        'lecturer_id',
        'hall_id',
        'section_capacity',
        'expected_student_count',
        'created_by',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'section_capacity' => 'integer',
        'expected_student_count' => 'integer',
    ];

    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ScheduleImportRow::class, 'schedule_import_row_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
