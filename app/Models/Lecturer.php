<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lecturer extends Model
{
    public const PLACEHOLDER_THEORETICAL = 'theoretical';

    public const PLACEHOLDER_PRACTICAL = 'practical';

    protected $fillable = [
        'user_id',
        'lecturer_id',
        'name',
        'name_en',
        'canonical_name',
        'placeholder_type',
        'email',
        'phone',
        'department_id',
        'title',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $lecturer): void {
            if ($lecturer->placeholder_type !== null
                && ! in_array($lecturer->placeholder_type, self::placeholderTypes(), true)) {
                throw new \InvalidArgumentException("Unsupported lecturer placeholder type [{$lecturer->placeholder_type}].");
            }

            if ($lecturer->isPlaceholder() && $lecturer->user_id !== null) {
                throw new \InvalidArgumentException('A placeholder lecturer cannot be linked to a user account.');
            }
        });
    }

    /** @return array<int, string> */
    public static function placeholderTypes(): array
    {
        return [self::PLACEHOLDER_THEORETICAL, self::PLACEHOLDER_PRACTICAL];
    }

    public function isPlaceholder(): bool
    {
        return in_array($this->placeholder_type, self::placeholderTypes(), true);
    }

    public function isTheoreticalPlaceholder(): bool
    {
        return $this->placeholder_type === self::PLACEHOLDER_THEORETICAL;
    }

    public function isPracticalPlaceholder(): bool
    {
        return $this->placeholder_type === self::PLACEHOLDER_PRACTICAL;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(SubjectSectionScheduleSlot::class);
    }
}
