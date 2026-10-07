<?php

namespace App\Enums\Constraint;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ConstraintStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case ACCEPTED = 'accepted';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::SUBMITTED => __('Submitted'),
            self::ACCEPTED => __('Accepted'),
        };
    }

    /**
     * Submitted and accepted programs are read-only until a faculty admin returns them.
     */
    public function locks(): bool
    {
        return match ($this) {
            self::DRAFT => false,
            self::SUBMITTED, self::ACCEPTED => true,
        };
    }
}
