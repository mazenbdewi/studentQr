<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;

    use HasPanelShield;
    use HasRoles;
    use Notifiable;

    protected $hidden = [
        // 'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function canAccessFilament(): bool
    {
        return $this->hasAnyRole([
            'super_admin',
            'course_lecturer',
            'manager',
        ]);
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->email === 'super@admin.com' || $this->hasRole(['super_admin', 'manager', 'course_lecturer']);
    }


    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function devices()
    {
        return $this->hasMany(StudentDevice::class, 'student_id');
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }


    public function lectureSessions()
    {
        return $this->hasMany(LectureSession::class, 'lecturer_id');
    }

    public function headedDepartment()
    {
        return $this->hasOne(Department::class, 'head_of_department');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, Enrollment::class)
            ->withPivot(['semester', 'year', 'status'])
            ->withTimestamps();
    }
}
