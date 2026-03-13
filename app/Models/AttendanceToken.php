<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'lecture_session_id',
        'token_type',
        'token_value',
        'qr_image_path',
        'expires_at',
        'is_used',
        'used_by',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
        'used_at' => 'datetime',
    ];

    public function lectureSession()
    {
        return $this->belongsTo(LectureSession::class, 'lecture_session_id');
    }
}
