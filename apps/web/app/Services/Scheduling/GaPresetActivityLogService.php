<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\GaParameterPreset;

readonly class GaPresetActivityLogService
{
    public const PRESET_CREATED = 'ga_preset.created';

    public const PRESET_UPDATED = 'ga_preset.updated';

    public const PRESET_DELETED = 'ga_preset.deleted';

    private const LOG_NAME = 'scheduling';

    public function created(GaParameterPreset $preset): void
    {
        $this->log($preset, self::PRESET_CREATED, __('Created GA preset :name', ['name' => $preset->name]));
    }

    public function updated(GaParameterPreset $preset): void
    {
        if (! $preset->wasChanged()) {
            return;
        }

        $changed = array_diff(array_keys($preset->getChanges()), ['updated_at']);

        activity(self::LOG_NAME)
            ->performedOn($preset)
            ->event(self::PRESET_UPDATED)
            ->withChanges([
                'old' => array_intersect_key($preset->getOriginal(), array_flip($changed)),
                'attributes' => array_intersect_key($preset->getAttributes(), array_flip($changed)),
            ])
            ->log(__('Updated GA preset :name', ['name' => $preset->name]));
    }

    public function deleted(GaParameterPreset $preset): void
    {
        $this->log($preset, self::PRESET_DELETED, __('Deleted GA preset :name', ['name' => $preset->name]));
    }

    private function log(GaParameterPreset $preset, string $event, string $description): void
    {
        activity(self::LOG_NAME)->performedOn($preset)->event($event)->log($description);
    }
}