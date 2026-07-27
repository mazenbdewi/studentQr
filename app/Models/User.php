<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use HasPanelShield;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'login_username',
        'password',
        'must_change_password',
        'pin_code',
        'pin_enabled',
        'pin_changed_at',
        'role',
        'type',
        'status',
        'title',
        'faculty_id',
        'department_id',
        'year',
        'phone',
        'student_number',
        'activation_code',
        'activation_expires',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'pin_code',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'is_active' => 'boolean',
        'pin_enabled' => 'boolean',
        'pin_changed_at' => 'datetime',
    ];

    public function hasPinCode(): bool
    {
        return filled($this->getRawOriginal('pin_code'));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->login_username = strtolower(trim((string) $user->login_username));
        });
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageBackups(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canViewActivityLogs(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canAccessFilament(): bool
    {
        return $this->hasAnyRole([
            'super-admin',
            'admin',
            'course_lecturer',
            'manager',
        ]);
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(['admin', 'manager', 'course_lecturer']);
    }

    public function syncSystemRole(string $role): void
    {
        $mappedRole = $this->mapDatabaseRoleToSpatieRole($role);

        foreach (self::systemSpatieRoles() as $systemRole) {
            if ($this->hasRole($systemRole) && $systemRole !== $mappedRole) {
                $this->removeRole($systemRole);
            }
        }

        if (! $this->hasRole($mappedRole)) {
            $this->assignRole($mappedRole);
        }
    }

    public static function mapDatabaseRoleToSpatieRole(string $role): string
    {
        return match ($role) {
            'super_admin' => 'super-admin',
            'admin' => 'admin',
            'manager', 'attendance_monitor' => 'manager',
            'course_lecturer' => 'course_lecturer',
            'student' => 'student',
            default => throw new \InvalidArgumentException("Unsupported user role classification [{$role}]."),
        };
    }

    /** @return array<int, string> */
    private static function systemSpatieRoles(): array
    {
        return ['super-admin', 'admin', 'manager', 'course_lecturer', 'student'];
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
        return $this->belongsTo(Faculty::class)->withTrashed();
    }

    public function department()
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function lectureSessions()
    {
        return $this->hasMany(LectureSession::class, 'lecturer_id');
    }

    /** @return HasMany<Lecturer, $this> */
    public function lecturerIdentities(): HasMany
    {
        return $this->hasMany(Lecturer::class, 'user_id');
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
