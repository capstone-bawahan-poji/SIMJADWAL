<?php

namespace App\Rules\MasterData;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * For PATCH fields that cannot change (a course's owner, an assignment's course):
 * resending the current value passes, so a client may submit the whole form.
 */
readonly class Unchanged implements ValidationRule
{
    public function __construct(private mixed $current) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Loose on purpose: "1", 1 and true are the same value in a form or query string.
        if ($value != $this->current) {
            $fail(__('This field cannot be changed.'));
        }
    }
}
