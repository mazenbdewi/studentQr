<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Seminar extends Model
{
    protected $fillable = [
        'created_by',
        'title',
        'audience_type',
        'location',
        'starts_at',
        'ends_at',
        'status',
        'qr_token',
        'qr_started_at',
        'qr_expires_at',
        'qr_expired',
        'description',
        'collect_specialization',
        'collect_profession',
        'collect_academic_rank',
        'collect_age',
        'collect_organization',
        'collect_phone',
        'collect_email',
        'collect_notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'qr_started_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'qr_expired' => 'boolean',
        'collect_specialization' => 'boolean',
        'collect_profession' => 'boolean',
        'collect_academic_rank' => 'boolean',
        'collect_age' => 'boolean',
        'collect_organization' => 'boolean',
        'collect_phone' => 'boolean',
        'collect_email' => 'boolean',
        'collect_notes' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SeminarAttendance::class);
    }

    public function canManage(?Authenticatable $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole('super-admin')
            || (int) $user->getAuthIdentifier() === (int) $this->created_by;
    }

    public function activateQr(): void
    {
        $expiresAt = $this->ends_at && $this->ends_at->isFuture()
            ? $this->ends_at
            : now()->addHours(8);

        $this->update([
            'status' => 'active',
            'qr_token' => $this->qr_token ?: Str::random(40),
            'qr_started_at' => $this->qr_started_at ?: now(),
            'qr_expires_at' => $expiresAt,
            'qr_expired' => false,
        ]);
    }

    public function syncLifecycleState(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (! $this->qr_expires_at || $this->qr_expires_at->isFuture()) {
            return false;
        }

        $this->update([
            'status' => 'completed',
            'qr_expired' => true,
        ]);

        return true;
    }
}
