<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LectureSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subject_id',
        'subject_section_id',
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

    protected static function booted(): void
    {
        static::saving(function (self $session): void {
            if (blank($session->subject_section_id)) {
                return;
            }

            $section = SubjectSection::query()->find($session->subject_section_id);

            if (! $section || (int) $section->subject_id !== (int) $session->subject_id) {
                throw ValidationException::withMessages([
                    'subject_section_id' => __('subjects.section_must_belong_to_subject'),
                ]);
            }
        });
    }

    public function canManageQr(?Authenticatable $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        return $user->hasRole('course_lecturer')
            && (int) $user->getAuthIdentifier() === (int) $this->lecturer_id;
    }

    public function shouldShowQrAction(?Authenticatable $user): bool
    {
        return $this->canManageQr($user)
            && in_array($this->status, ['scheduled', 'active'], true)
            && $this->isWithinQrAvailabilityWindow();
    }

    public function scheduledStartAt(): ?Carbon
    {
        if (! $this->session_date || ! $this->start_time) {
            return null;
        }

        $sessionDate = $this->session_date instanceof CarbonInterface
            ? $this->session_date->copy()
            : Carbon::parse($this->session_date);

        $startTime = $this->start_time instanceof CarbonInterface
            ? $this->start_time->format('H:i:s')
            : (string) $this->start_time;

        return Carbon::parse($sessionDate->toDateString().' '.$startTime);
    }

    public function qrAvailableFromAt(): ?Carbon
    {
        return $this->scheduledStartAt()?->subMinutes(5);
    }

    public function isWithinQrAvailabilityWindow(?CarbonInterface $reference = null): bool
    {
        $reference ??= now();

        $availableFrom = $this->qrAvailableFromAt();
        $scheduledEndAt = $this->scheduledEndAt();

        if (! $availableFrom || ! $scheduledEndAt) {
            return false;
        }

        return $reference->greaterThanOrEqualTo($availableFrom)
            && $reference->lessThan($scheduledEndAt);
    }

    public function scheduledEndAt(): ?Carbon
    {
        if (! $this->session_date || ! $this->end_time) {
            return null;
        }

        $sessionDate = $this->session_date instanceof CarbonInterface
            ? $this->session_date->copy()
            : Carbon::parse($this->session_date);

        $endTime = $this->end_time instanceof CarbonInterface
            ? $this->end_time->format('H:i:s')
            : (string) $this->end_time;

        return Carbon::parse($sessionDate->toDateString().' '.$endTime);
    }

    public function hasReachedScheduledEnd(?CarbonInterface $reference = null): bool
    {
        $scheduledEndAt = $this->scheduledEndAt();

        if (! $scheduledEndAt) {
            return false;
        }

        return ($reference ?? now())->greaterThanOrEqualTo($scheduledEndAt);
    }

    public function hasExpiredQrWindow(?CarbonInterface $reference = null): bool
    {
        if (! $this->qr_expires_at) {
            return false;
        }

        $qrExpiresAt = $this->qr_expires_at instanceof CarbonInterface
            ? $this->qr_expires_at
            : Carbon::parse($this->qr_expires_at);

        return ($reference ?? now())->greaterThanOrEqualTo($qrExpiresAt);
    }

    public function shouldBeAutomaticallyClosed(?CarbonInterface $reference = null): bool
    {
        if (in_array($this->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        return $this->hasReachedScheduledEnd($reference);
    }

    public function syncLifecycleState(?CarbonInterface $reference = null, bool $refresh = true): bool
    {
        $reference ??= now();

        if ($refresh) {
            $this->refresh();
        }

        if (! $this->shouldBeAutomaticallyClosed($reference)) {
            return false;
        }

        $this->update([
            'status' => 'completed',
            'qr_expired' => true,
            'actual_end' => $this->actual_end ?? $this->scheduledEndAt() ?? $reference,
        ]);

        if ($refresh) {
            $this->refresh();
        }

        return true;
    }

    public static function syncExpiredSessions(?CarbonInterface $reference = null): void
    {
        $reference ??= now();
        $today = $reference->toDateString();
        $currentTime = $reference->format('H:i:s');

        static::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(function (Builder $query) use ($today, $currentTime) {
                $query
                    ->where(function (Builder $query) use ($today, $currentTime) {
                        $query
                            ->whereDate('session_date', '<', $today)
                            ->orWhere(function (Builder $query) use ($today, $currentTime) {
                                $query
                                    ->whereDate('session_date', $today)
                                    ->whereTime('end_time', '<=', $currentTime);
                            });
                    });
            })
            ->get()
            ->each(fn (self $session) => $session->syncLifecycleState($reference, refresh: false));
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->email === 'super@admin.com' || $this->hasRole(['super_admin', 'manager', 'course_lecturer']);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function subjectSection(): BelongsTo
    {
        return $this->belongsTo(SubjectSection::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id')->withTrashed();
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class)->withTrashed();
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'enrollments', 'subject_id', 'student_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'lecture_session_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(AttendanceToken::class);
    }
}
