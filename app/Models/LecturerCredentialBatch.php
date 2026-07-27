<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LecturerCredentialBatch extends Model
{
    protected $fillable = ['uuid', 'academic_term_id', 'batch_type', 'original_filename', 'encrypted_file_path', 'record_count', 'sha256', 'encryption_key_version', 'generated_by', 'generated_at', 'downloaded_count', 'last_downloaded_at', 'status', 'deleted_at', 'deleted_by'];

    protected $casts = ['generated_at' => 'datetime', 'last_downloaded_at' => 'datetime', 'downloaded_count' => 'integer', 'record_count' => 'integer'];

    protected static function booted(): void
    {
        static::creating(fn (self $batch) => $batch->uuid ??= (string) Str::uuid());
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(LecturerCredentialBatchAction::class);
    }
}
