<?php

namespace App\Models\Concerns;

use App\Services\System\AuditLogger;
use Illuminate\Support\Arr;

/**
 * Automatically records created, updated and deleted events for a model.
 *
 * Hidden attributes (passwords, tokens) and attributes listed in `auditExclude()` are never written to the log.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            app(AuditLogger::class)->log('created', $model, null, [], $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (self $model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $original = Arr::only($model->getOriginal(), array_keys($changes));

            app(AuditLogger::class)->log('updated', $model, null, $model->auditableAttributes($original), $changes);
        });

        static::deleted(function (self $model): void {
            app(AuditLogger::class)->log('deleted', $model, null, $model->auditableAttributes($model->getAttributes()));
        });
    }

    /**
     * Attributes that are never written to the audit log, in addition to hidden attributes.
     *
     * @return list<string>
     */
    public function auditExclude(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        return Arr::except($attributes, [
            ...$this->getHidden(),
            ...$this->auditExclude(),
            'created_at',
            'updated_at',
        ]);
    }
}
