<?php

namespace App\Extensions\Data\Traits;

use Carbon\CarbonImmutable;

trait WithTimestamps
{
    public ?CarbonImmutable $createdAt = null;

    public ?CarbonImmutable $updatedAt = null;
}
