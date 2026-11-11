<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\Account\Role;
use App\Models\MasterData\StudyProgram;
use Illuminate\Validation\Rule;

/**
 * A GA schedule belongs to one study program, or to the institute when it is the TPB schedule.
 * admin_tpb always works on TPB, admin_prodi on their own program. superadmin names a program or
 * sends scope=tpb; admin_fakultas names a program of their faculty (read only, the policy checks it).
 */
trait ResolvesScheduleScope
{
    /**
     * @return array<string, mixed>
     */
    protected function scheduleScopeRules(): array
    {
        return [
            'scope' => ['nullable', Rule::in(['study_program', 'tpb'])],
            'study_program_id' => [
                Rule::requiredIf(fn () => in_array($this->user()->role, [Role::SUPER_ADMIN, Role::FACULTY_ADMIN], true) && ! $this->isTpbScope()),
                'nullable', 'integer', Rule::exists(StudyProgram::class, 'id')->withoutTrashed(),
            ],
        ];
    }

    /**
     * The program of the schedule, null for the TPB schedule.
     */
    public function studyProgramId(): ?int
    {
        return match ($this->user()->role) {
            Role::TPB_ADMIN => null,
            Role::STUDY_PROGRAM_ADMIN => $this->user()->study_program_id,
            default => $this->isTpbScope() || ! is_numeric($this->input('study_program_id')) ? null : (int) $this->input('study_program_id'),
        };
    }

    public function studyProgram(): ?StudyProgram
    {
        $id = $this->studyProgramId();

        return $id === null ? null : StudyProgram::query()->findOrFail($id);
    }

    /**
     * For rules(), which run before study_program_id is validated: an unknown id gives null.
     */
    protected function studyProgramOrNull(): ?StudyProgram
    {
        $id = $this->studyProgramId();

        return $id === null ? null : StudyProgram::query()->find($id);
    }

    public function isTpbScope(): bool
    {
        return $this->user()->role === Role::TPB_ADMIN
            || ($this->user()->role === Role::SUPER_ADMIN && $this->input('scope') === 'tpb');
    }
}
