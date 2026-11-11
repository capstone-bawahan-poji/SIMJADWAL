<?php

namespace App\Http\Requests\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\MasterData\Faculty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;

/**
 * The faculty a request works on: the one named by faculty_id for those who read every faculty
 * (superadmin, TPB admin), else the user's own (a program admin's is their program's faculty).
 * The controller still authorizes the action on it.
 */
trait ResolvesFacultyScope
{
    /**
     * @return array<string, mixed>
     */
    protected function facultyScopeRules(): array
    {
        return [
            'faculty_id' => [
                Rule::requiredIf(fn () => $this->readsAllFaculties()),
                'nullable', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed(),
            ],
        ];
    }

    public function faculty(): Faculty
    {
        $id = $this->readsAllFaculties()
            ? (int) $this->validated('faculty_id')
            : app(ResolvesOrgScope::class)->facultyId($this->user());

        return $id === null ? throw new AuthorizationException : Faculty::query()->findOrFail($id);
    }

    private function readsAllFaculties(): bool
    {
        return app(ResolvesOrgScope::class)->readsAllFaculties($this->user());
    }
}
