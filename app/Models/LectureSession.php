<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LectureSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subject_id',
        'academic_term_id',
        'subject_section_id',
        'subject_section_schedule_slot_id',
        'lecture_session_generation_run_id',
        'generated_from_weekly_schedule_at',
        'lecturer_id',
        'hall_id',
        'session_date',
        'start_time',
        'end_time',
        'status',
        'attendance_mode',
        'qr_refresh_rate',
        'expected_students',
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
        'generated_from_weekly_schedule_at' => 'datetime',
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

    public function scopeForAcademicTerm(Builder $query, int $academicTermId): Builder
    {
        return $query->where('academic_term_id', $academicTermId);
    }

    public function scopeForCurrentAcademicTerm(Builder $query): Builder
    {
        $id = app(\App\Support\AcademicTermContext::class)->currentId();

        return $id === null ? $query->whereRaw('1 = 0') : $this->scopeForAcademicTerm($query, $id);
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
        return $this->scheduledAt('start_time');
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
        return $this->scheduledAt('end_time');
    }

    private function scheduledAt(string $timeColumn): ?Carbon
    {
        $date = $this->getRawOriginal('session_date');
        $time = $this->getRawOriginal($timeColumn);

        if (! is_string($date) || ! is_string($time) || $date === '' || $time === '') {
            return null;
        }

        return Carbon::parse($date, config('app.timezone'))
            ->setTimeFromTimeString($time);
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

        $qrExpiresAt = $this->qr_expires_at;

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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function subjectSection(): BelongsTo
    {
        return $this->belongsTo(SubjectSection::class);
    }

    public function sourceWeeklyScheduleSlot(): BelongsTo
    {
        return $this->belongsTo(SubjectSectionScheduleSlot::class, 'subject_section_schedule_slot_id');
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(LectureSessionGenerationRun::class, 'lecture_session_generation_run_id');
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
