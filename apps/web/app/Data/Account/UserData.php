<?php

namespace App\Data\Account;

use App\Data\BaseData;
use App\Enums\Account\Role;
use App\Extensions\Data\FromPolicy;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class UserData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?Role $role,
        public ?int $facultyId,
        public ?int $studyProgramId,
        public ?int $lecturerId,
        public bool $isActive,
        public ?CarbonImmutable $createdAt,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('updateStatus')]
        public bool $canUpdateStatus = false,
    ) {}

    public static function relations(): array
    {
        return ['roles', 'lecturer:id,user_id'];
    }
}
