<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Records created and updated events of a model into the activity log.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (self $model): void {
            $model->writeActivity($model->activityPrefix().'.created', null, $model->loggableAttributes($model->getAttributes()));
        });

        static::updated(function (self $model): void {
            $changes = $model->loggableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $old = [];

            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getRawOriginal($key);
            }

            $model->writeActivity($model->activityPrefix().'.updated', $old, $changes);
        });
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest('created_at')->latest('id');
    }

    abstract protected function activityPrefix(): string;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function loggableAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(['id', 'created_at', 'updated_at', 'deleted_at', 'updated_by']));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeActivity(string $action, ?array $old, array $new): void
    {
        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
