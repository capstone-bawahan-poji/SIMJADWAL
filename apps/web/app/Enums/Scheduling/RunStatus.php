<?php

namespace App\Enums\Scheduling;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RunStatus: string
{
    case QUEUED = 'queued';
    case RUNNING = 'running';
    case DONE = 'done';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => __('Queued'),
            self::RUNNING => __('Running'),
            self::DONE => __('Done'),
            self::FAILED => __('Failed'),
        };
    }
}
