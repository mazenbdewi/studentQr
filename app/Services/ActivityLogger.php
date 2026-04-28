<?php

namespace App\Services;

use App\Jobs\LogActivityJob;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivityLogger
{
    public function log(array $payload, bool $heavy = false): void
    {
        if (! config('activity-log.enabled')) {
            return;
        }

        $category = $payload['category'] ?? null;

        if ($category && ! config("activity-log.categories.{$category}", false)) {
            return;
        }

        try {
            $normalizedPayload = $this->normalizePayload($payload);

            if (
                $heavy
                && config('activity-log.use_queue_for_heavy_logs')
            ) {
                LogActivityJob::dispatch($normalizedPayload);

                return;
            }

            $this->writeNow($normalizedPayload);
        } catch (Throwable $exception) {
            $this->reportFailure($exception, $payload);
        }
    }

    public function writeNow(array $payload): void
    {
        if (config('activity-log.log_to_file')) {
            Log::info('Activity log', Arr::except($payload, ['old_values', 'new_values', 'context', 'metadata']));
        }

        try {
            if (config('activity-log.log_to_database', true)) {
                AuditLog::query()->create($this->normalizePayload($payload));
            }
        } catch (Throwable $exception) {
            $this->reportFailure($exception, $payload);
        }
    }

    public function logModelCreated(Model $model, string $category, ?string $description = null, array $context = []): void
    {
        $this->log([
            'category' => $category,
            'action' => 'create',
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'description' => $description,
            'new_values' => $this->extractModelValues($model),
            'context' => $context,
        ]);
    }

    public function logModelUpdated(Model $model, array $original, string $category, ?string $description = null, array $context = []): void
    {
        [$oldValues, $newValues] = $this->extractChangedValues($model, $original);

        if ($oldValues === [] && $newValues === []) {
            return;
        }

        $this->log([
            'category' => $category,
            'action' => 'update',
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => $context,
        ]);
    }

    public function logModelDeleted(Model $model, string $category, ?string $description = null, array $context = []): void
    {
        $this->log([
            'category' => $category,
            'action' => 'delete',
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'description' => $description,
            'old_values' => $this->extractModelValues($model),
            'context' => $context,
        ]);
    }

    public function logImportSummary(
        string $category,
        string $importType,
        ?string $fileName,
        int $totalRows,
        int $successfulRows,
        int $failedRows,
        string $startedAt,
        string $finishedAt,
        array $context = []
    ): void {
        $this->log([
            'category' => $category,
            'action' => 'import',
            'description' => $importType,
            'new_values' => [
                'file_name' => $fileName,
                'import_type' => $importType,
                'total_rows' => $totalRows,
                'successful_rows' => $successfulRows,
                'failed_rows' => $failedRows,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
            ],
            'context' => $context,
        ], heavy: true);
    }

    public function logExport(
        string $category,
        string $exportType,
        ?string $fileName = null,
        ?Model $model = null,
        array $context = []
    ): void {
        $this->log([
            'category' => $category,
            'action' => 'export',
            'model_type' => $model ? $model::class : null,
            'model_id' => $model?->getKey(),
            'description' => $exportType,
            'new_values' => array_filter([
                'file_name' => $fileName,
                'export_type' => $exportType,
            ], fn ($value) => $value !== null && $value !== ''),
            'context' => $context,
        ], heavy: true);
    }

    public function logAuth(string $action, ?string $description = null, array $context = []): void
    {
        $this->log([
            'category' => 'auth',
            'action' => $action,
            'description' => $description,
            'context' => $context,
        ]);
    }

    public function logAttendance(string $action, array $context = []): void
    {
        $this->log([
            'category' => 'attendance',
            'action' => $action,
            'description' => $action,
            'context' => Arr::only($context, [
                'student_id',
                'lecture_session_id',
                'status',
                'reason',
            ]),
        ]);
    }

    public function logSettingsChange(array $oldValues, array $newValues, ?string $description = null): void
    {
        $this->log([
            'category' => 'settings',
            'action' => 'settings_changed',
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function logRoleChange(Model $model, array $oldValues, array $newValues, ?string $description = null): void
    {
        $this->log([
            'category' => 'permissions',
            'action' => 'role_changed',
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function extractModelValues(Model $model, ?array $attributes = null): array
    {
        $attributes ??= $model->attributesToArray();

        return $this->sanitizeArray(
            Arr::only($attributes, $this->allowedModelFields($model, array_keys($attributes)))
        );
    }

    public function extractChangedValues(Model $model, array $original): array
    {
        $changes = $model->getChanges();

        unset($changes['updated_at'], $changes['created_at'], $changes['deleted_at']);

        $changedKeys = array_values(array_intersect(
            array_keys($changes),
            $this->allowedModelFields($model, array_keys($changes))
        ));

        $oldValues = [];
        $newValues = [];

        foreach ($changedKeys as $key) {
            $oldValues[$key] = $original[$key] ?? null;
            $newValues[$key] = $model->getAttribute($key);
        }

        return [
            $this->sanitizeArray($oldValues),
            $this->sanitizeArray($newValues),
        ];
    }

    private function normalizePayload(array $payload): array
    {
        $request = request();
        $user = auth()->user();

        $normalized = [
            'user_id' => $payload['user_id'] ?? $user?->id,
            'category' => $payload['category'] ?? 'general',
            'action' => $payload['action'] ?? 'unknown',
            'model_type' => $payload['model_type'] ?? null,
            'model_id' => $payload['model_id'] ?? null,
            'description' => $this->limitString($payload['description'] ?? null, 1000),
            'old_values' => $this->sanitizeArray($payload['old_values'] ?? []),
            'new_values' => $this->sanitizeArray($payload['new_values'] ?? []),
            'context' => $this->sanitizeArray($payload['context'] ?? []),
            'ip_address' => $payload['ip_address'] ?? $request?->ip(),
            'user_agent' => $this->limitString($payload['user_agent'] ?? $request?->userAgent(), 512),
            'severity' => $payload['severity'] ?? 'info',
            'user_type' => $payload['user_type'] ?? ($user ? $user::class : null),
            'location' => $payload['location'] ?? null,
            'metadata' => $this->sanitizeArray($payload['metadata'] ?? []),
        ];

        if ($normalized['old_values'] === []) {
            $normalized['old_values'] = null;
        }

        if ($normalized['new_values'] === []) {
            $normalized['new_values'] = null;
        }

        if ($normalized['context'] === []) {
            $normalized['context'] = null;
        }

        if ($normalized['metadata'] === []) {
            $normalized['metadata'] = null;
        }

        return $normalized;
    }

    private function allowedModelFields(Model $model, array $availableKeys): array
    {
        $fillable = method_exists($model, 'getFillable') ? $model->getFillable() : [];
        $defaultKeys = $fillable !== [] ? $fillable : $availableKeys;

        return array_values(array_diff($defaultKeys, config('activity-log.excluded_fields', [])));
    }

    private function sanitizeArray(array $values, int $depth = 0): array
    {
        if ($depth >= (int) config('activity-log.max_depth', 2)) {
            return [];
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            if (in_array((string) $key, config('activity-log.excluded_fields', []), true)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->sanitizeArray($value, $depth + 1);

                if ($nested !== []) {
                    $sanitized[$key] = $nested;
                }

                continue;
            }

            if ($value instanceof Model) {
                continue;
            }

            if (is_object($value)) {
                $value = method_exists($value, '__toString') ? (string) $value : get_class($value);
            }

            if (is_string($value)) {
                $sanitized[$key] = $this->limitString($value, (int) config('activity-log.max_value_length', 255));

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function limitString(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit - 3) . '...'
            : $value;
    }

    private function reportFailure(Throwable $exception, array $payload): void
    {
        Log::warning('Activity logging failed.', [
            'message' => $exception->getMessage(),
            'action' => $payload['action'] ?? null,
            'category' => $payload['category'] ?? null,
        ]);
    }
}
