<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    protected $fillable = [
        'display_name',
        'canonical_name',
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
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

    /** @return HasMany<ScheduleImportRow, $this> */
    public function scheduleImportRows(): HasMany
    {
        return $this->hasMany(ScheduleImportRow::class);
    }
}
