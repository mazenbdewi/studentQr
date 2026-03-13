<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LectureSession extends Model
{
    protected $fillable = [
        'subject_id',
        'lecturer_id',
        'hall_id',
        'session_date',
        'start_time',
        'end_time',
        'status',
        'attendance_mode',
        'qr_refresh_rate',
        'notes',
        'session_otp',
        'qr_expired',
        'qr_started_at',
        'qr_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'qr_started_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'session_date' => 'date',
        'qr_expired' => 'boolean',
    ];

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->email === 'super@admin.com' || $this->hasRole(['super_admin', 'manager', 'course_lecturer']);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function hall()
    {
        return $this->belongsTo(Hall::class);
    }

    // public function attendances()
    // {
    //     return $this->hasMany(Attendance::class);
    // }
    public function students()
    {
        return $this->belongsToMany(Student::class, 'subject_student', 'subject_id', 'student_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'lecture_session_id');
    }

    public function tokens()
    {
        return $this->hasMany(AttendanceToken::class);
    }
}
