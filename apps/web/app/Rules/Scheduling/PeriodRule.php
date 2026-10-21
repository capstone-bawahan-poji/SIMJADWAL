<?php

namespace App\Rules\Scheduling;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Academic period: YYYY/YYYY-GANJIL or YYYY/YYYY-GENAP with consecutive years.
 */
class PeriodRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})\/(\d{4})-(GANJIL|GENAP)$/', $value, $match) || (int) $match[2] !== (int) $match[1] + 1) {
            $fail(__('The period must use the format YYYY/YYYY-GANJIL or YYYY/YYYY-GENAP with consecutive years.'));
        }
    }
}
