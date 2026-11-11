<?php

namespace App\Enums\Scheduling;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RunSource: string
{
    case GA = 'ga';
    case IMPORT = 'import';
}
