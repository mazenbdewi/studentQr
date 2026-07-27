<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturerPasswordResetOperation extends Model
{
    protected $fillable = [
        'fingerprint', 'academic_term_id', 'performed_by', 'selected_count', 'eligible_count',
        'excluded_count', 'status', 'safe_metadata', 'completed_at',
    ];

    protected $casts = ['safe_metadata' => 'array', 'completed_at' => 'datetime'];
}
