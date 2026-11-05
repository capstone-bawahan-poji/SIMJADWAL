<?php

namespace App\Extensions\Models\Traits;

use App\Services\Shared\ChangeRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every create, update, delete and restore of the model with ChangeRecorder, so the
 * web and desktop clients hear about it. Query builder writes (insert, update, delete on a
 * query, sync) skip model events: call ChangeRecorder::record() next to them.
 */
trait BroadcastsChanges
{
    public static function bootBroadcastsChanges(): void
    {
        $record = fn (Model $model) => app(ChangeRecorder::class)->record($model->getTable(), $model->getKey());

        static::created($record);
        static::deleted($record);
        static::updated(function (Model $model) use ($record) {
            $changed = array_diff(array_keys($model->getChanges()), [...$model->broadcastIgnoredAttributes(), $model->getUpdatedAtColumn()]);

            if ($changed !== []) {
                $record($model);
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored($record);
        }
    }

    /**
     * Attributes whose change alone is not worth telling the clients about.
     *
     * @return list<string>
     */
    public function broadcastIgnoredAttributes(): array
    {
        return [];
    }
}