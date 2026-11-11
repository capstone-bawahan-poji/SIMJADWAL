<?php

namespace App\Enums\Scheduling;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SubmissionStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case RETURNED = 'returned';
    case ACCEPTED = 'accepted';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::SUBMITTED => __('Submitted'),
            self::RETURNED => __('Returned'),
            self::ACCEPTED => __('Accepted'),
            self::PUBLISHED => __('Published'),
        };
    }

    /**
     * The program may still run the GA, activate a run and move meetings.
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT || $this === self::RETURNED;
    }
}
