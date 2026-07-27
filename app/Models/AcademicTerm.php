<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    protected $fillable = [
        'display_name',
        'canonical_name',
        'teaching_start_date',
        'teaching_end_date',
        'is_archived',
    ];

    protected $casts = [
        'teaching_start_date' => 'date',
        'teaching_end_date' => 'date',
        'is_archived' => 'boolean',
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function scopeArchivedTerms(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    public function scopeCurrentTerm(Builder $query): Builder
    {
        $id = app(\App\Support\AcademicTermContext::class)->currentId();

        return $id === null ? $query->whereRaw('1 = 0') : $query->whereKey($id);
    }

    public function subjectSections(): HasMany
    {
        return $this->hasMany(SubjectSection::class);
    }

    public function importBatches(): BelongsToMany
    {
        return $this->belongsToMany(ImportBatch::class, 'import_batch_academic_term')
            ->withPivot('row_count');
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(SubjectSectionScheduleSlot::class);
    }

    public function lectureSessions(): HasMany
    {
        return $this->hasMany(LectureSession::class);
    }

    public function lectureSessionGenerationRuns(): HasMany
    {
        return $this->hasMany(LectureSessionGenerationRun::class);
    }

    /** @return HasMany<ScheduleImportRow, $this> */
    public function scheduleImportRows(): HasMany
    {
        return $this->hasMany(ScheduleImportRow::class);
    }
}
