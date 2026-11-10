<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\Account\Role;
use App\Models\MasterData\Faculty;
use Illuminate\Validation\Rule;

/**
 * A schedule belongs to one faculty, or to the institute when it is the TPB schedule (faculty null).
 * superadmin names a faculty or sends scope=tpb, admin_tpb always works on TPB, everyone else
 * on their own faculty.
 */
trait ResolvesScheduleScope
{
    /**
     * @return array<string, mixed>
     */
    protected function scheduleScopeRules(): array
    {
        return [
            'scope' => ['nullable', Rule::in(['faculty', 'tpb'])],
            'faculty_id' => [
                Rule::requiredIf(fn () => $this->user()->role === Role::SUPER_ADMIN && ! $this->isTpbScope()),
                'nullable', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed(),
            ],
        ];
    }

    /**
     * The faculty of the schedule, null for the TPB schedule.
     */
    public function facultyId(): ?int
    {
        return match ($this->user()->role) {
            Role::SUPER_ADMIN => $this->isTpbScope() ? null : (is_numeric($this->input('faculty_id')) ? (int) $this->input('faculty_id') : null),
            Role::TPB_ADMIN => null,
            default => $this->user()->faculty_id,
        };
    }

    public function isTpbScope(): bool
    {
        return $this->user()->role === Role::TPB_ADMIN
            || ($this->user()->role === Role::SUPER_ADMIN && $this->input('scope') === 'tpb');
    }
}
