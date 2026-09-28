<?php

namespace App\Enums\Constraint;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ConstraintCode: string
{
    case HC1 = 'HC1';
    case HC2 = 'HC2';
    case HC3 = 'HC3';
    case HC4 = 'HC4';
    case SC_INGIN = 'SC_INGIN';
    case SC_HINDARI = 'SC_HINDARI';
    case SC_SKS = 'SC_SKS';

    public function category(): ConstraintCategory
    {
        return str_starts_with($this->value, 'HC') ? ConstraintCategory::HARD : ConstraintCategory::SOFT;
    }

    /**
     * Codes a lecturer preference row may use.
     *
     * @return list<self>
     */
    public static function preferenceCodes(): array
    {
        return [self::SC_INGIN, self::SC_HINDARI];
    }
}
