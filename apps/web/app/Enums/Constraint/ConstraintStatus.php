<?php

namespace App\Enums\Constraint;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ConstraintStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::SUBMITTED => __('Submitted'),
        };
    }
}
