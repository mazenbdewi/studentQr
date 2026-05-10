<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeminarAttendance extends Model
{
    protected $fillable = [
        'seminar_id',
        'full_name',
        'specialization',
        'profession',
        'academic_rank',
        'age',
        'professional_title',
        'organization',
        'phone',
        'email',
        'attended_at',
        'ip_address',
        'device_fingerprint',
        'notes',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
    ];

    public function seminar(): BelongsTo
    {
        return $this->belongsTo(Seminar::class);
    }
}
