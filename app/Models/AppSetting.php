<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const CURRENT_ACADEMIC_TERM_ID_KEY = 'current_academic_term_id';

    public const DEFAULT_QR_REFRESH_RATE_KEY = 'default_qr_refresh_rate';

    public const LECTURER_CAN_EDIT_LECTURE_SESSIONS_KEY = 'lecturer_can_edit_lecture_sessions';

    public const LECTURER_CAN_DELETE_LECTURE_SESSIONS_KEY = 'lecturer_can_delete_lecture_sessions';

    public const FALLBACK_QR_REFRESH_RATE = 120;

    public const MIN_QR_REFRESH_RATE = 10;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function value(string $key, ?string $default = null): ?string
    {
        return static::query()
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        $value = static::value($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function putBoolean(string $key, bool $value): self
    {
        return static::put($key, $value ? '1' : '0');
    }

    public static function integer(string $key, int $default = 0): int
    {
        $value = static::value($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    public static function defaultQrRefreshRate(): int
    {
        return max(
            self::MIN_QR_REFRESH_RATE,
            self::integer(self::DEFAULT_QR_REFRESH_RATE_KEY, self::FALLBACK_QR_REFRESH_RATE),
        );
    }

    public static function lecturerCanEditLectureSessions(): bool
    {
        return self::boolean(self::LECTURER_CAN_EDIT_LECTURE_SESSIONS_KEY);
    }

    public static function lecturerCanDeleteLectureSessions(): bool
    {
        return self::boolean(self::LECTURER_CAN_DELETE_LECTURE_SESSIONS_KEY);
    }

    public static function currentAcademicTermId(): ?int
    {
        $id = self::integer(self::CURRENT_ACADEMIC_TERM_ID_KEY);

        return $id > 0 ? $id : null;
    }
}
