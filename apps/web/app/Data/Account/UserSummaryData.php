<?php

namespace App\Data\Account;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class UserSummaryData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $identityNumber,
    ) {}
}
