<?php

namespace App\Extensions\Data;

use Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Attributes\InjectsPropertyValue;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Fills a boolean DTO property with Gate::allows($ability, $model),
 * so each row tells the frontend what the current user may do with it.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class FromPolicy implements InjectsPropertyValue
{
    public function __construct(public string $ability) {}

    public function resolve(DataProperty $dataProperty, mixed $payload, array $properties, CreationContext $creationContext): mixed
    {
        return $payload instanceof Model && Gate::allows($this->ability, $payload);
    }

    public function shouldBeReplacedWhenPresentInPayload(): bool
    {
        return true;
    }
}
