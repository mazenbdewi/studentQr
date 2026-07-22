<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LectureSessionGenerationRun extends Model
{
    protected $fillable = [
        'academic_term_id',
        'schedule_import_batch_id',
        'started_by',
        'teaching_start_date',
        'teaching_end_date',
        'status',
        'source_slot_count',
        'candidate_session_count',
        'created_session_count',
        'skipped_session_count',
        'blocked_slot_count',
        'conflict_count',
        'summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'teaching_start_date' => 'date',
        'teaching_end_date' => 'date',
        'source_slot_count' => 'integer',
        'candidate_session_count' => 'integer',
        'created_session_count' => 'integer',
        'skipped_session_count' => 'integer',
        'blocked_slot_count' => 'integer',
        'conflict_count' => 'integer',
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function scheduleImportBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'schedule_import_batch_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by')->withTrashed();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LectureSession::class);
    }
}
