<?php

namespace App\Enums\Scheduling;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SelectionOperator: string
{
    case TS = 'TS'; /** Tournament Selection. */
    case TSR = 'TSR'; /** Tournament Selection with Roulette Wheel. */
}
