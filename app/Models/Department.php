<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Department extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'faculty_id',
        'name',
        'code',
        'name_en',
        'description',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Department $department): void {
            if (filled($department->code)) {
                return;
            }

            do {
                $code = 'DEP-' . Str::upper(Str::random(8));
            } while (self::query()->where('code', $code)->exists());

            $department->code = $code;
        });
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class)->withTrashed();
    }
}
