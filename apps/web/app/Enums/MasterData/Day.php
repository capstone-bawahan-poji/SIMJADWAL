<?php

namespace App\Enums\MasterData;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum Day: int
{
    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;

    public function label(): string
    {
        return match ($this) {
            self::MONDAY => __('Monday'),
            self::TUESDAY => __('Tuesday'),
            self::WEDNESDAY => __('Wednesday'),
            self::THURSDAY => __('Thursday'),
            self::FRIDAY => __('Friday'),
        };
    }
}
