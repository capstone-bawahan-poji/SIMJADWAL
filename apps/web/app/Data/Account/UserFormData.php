<?php

namespace App\Data\Account;

use App\Enums\Account\Role;
use Spatie\LaravelData\Data;

class UserFormData extends Data
{
    /**
     * Scope ids that do not belong to the role must already be null (see UserScopeRules).
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
        public Role $role,
        public ?int $facultyId,
        public ?int $studyProgramId,
        public ?int $lecturerId,
    ) {}
}
