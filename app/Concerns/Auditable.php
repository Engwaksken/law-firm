<?php

namespace App\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create/update/delete/restore events to activity_logs.
 *
 * Values of hidden attributes and anything in $auditExclude are never stored;
 * only the fact that they changed is. Models holding confidential content
 * (case notes, documents) should list those columns in $auditExclude.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', $model->auditValues($model->getAttributes())));

        static::updated(function (Model $model) {
            $changes = collect($model->getChanges())->except(['updated_at']);
            if ($changes->isEmpty()) {
                return;
            }
            $model->writeAudit('updated', [
                'old' => $model->auditValues(collect($model->getOriginal())->only($changes->keys())->all()),
                'new' => $model->auditValues($changes->all()),
            ]);
        });

        static::deleted(fn (Model $model) => $model->writeAudit(
            method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting() ? 'deleted' : 'force-deleted'
        ));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => $model->writeAudit('restored'));
        }
    }

    /** @param array<string, mixed> $values */
    protected function auditValues(array $values): array
    {
        $excluded = array_merge($this->getHidden(), $this->auditExclude ?? []);

        return collect($values)
            ->except(['created_at', 'updated_at'])
            ->map(fn ($value, $key) => in_array($key, $excluded, true) ? '[redacted]' : $value)
            ->all();
    }

    protected function writeAudit(string $event, array $properties = []): void
    {
        ActivityLog::record(
            event: class_basename($this).'.'.$event,
            subject: $this,
            description: class_basename($this).' '.$event,
            properties: $properties,
        );
    }
}
