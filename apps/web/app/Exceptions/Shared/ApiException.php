<?php

namespace App\Exceptions\Shared;

use RuntimeException;

/**
 * Domain error rendered as {"message", "code", "errors"} with a fixed error code from the API contract.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self(__('auth.failed'), 'INVALID_CREDENTIALS', 401);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function validation(array $errors): self
    {
        return new self(__('The given data was invalid.'), 'VALIDATION_FAILED', 422, $errors);
    }

    /**
     * @param  array<string, int>  $references  entity => count
     */
    public static function resourceInUse(array $references = [], ?string $message = null): self
    {
        return new self($message ?? __('The resource is still referenced by other data.'), 'RESOURCE_IN_USE', 409, ['references' => $references]);
    }

    public static function invalidState(string $message): self
    {
        return new self($message, 'INVALID_STATE', 409);
    }

    public static function constraintsLocked(): self
    {
        return new self(__('Study program constraints are submitted and locked.'), 'CONSTRAINTS_LOCKED', 409);
    }
}
