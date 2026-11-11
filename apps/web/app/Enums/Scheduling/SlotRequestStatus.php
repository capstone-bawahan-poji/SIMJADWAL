<?php

namespace App\Enums\Scheduling;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SlotRequestStatus: string
{
    case PENDING = 'pending';
    case FULFILLED = 'fulfilled';
    case PARTIALLY_FULFILLED = 'partially_fulfilled';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::FULFILLED => __('Fulfilled'),
            self::PARTIALLY_FULFILLED => __('Partially fulfilled'),
            self::REJECTED => __('Rejected'),
        };
    }
}
