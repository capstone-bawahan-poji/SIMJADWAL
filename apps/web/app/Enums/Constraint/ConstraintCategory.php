<?php

namespace App\Enums\Constraint;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ConstraintCategory: string
{
    case HARD = 'HC';
    case SOFT = 'SC';
}
