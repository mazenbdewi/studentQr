<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
}
