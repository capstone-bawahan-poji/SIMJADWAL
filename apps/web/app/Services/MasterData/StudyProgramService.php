<?php

namespace App\Services\MasterData;

use App\Data\MasterData\StudyProgramData;
use App\Data\MasterData\StudyProgramFormData;
use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Department;
use App\Models\MasterData\StudyProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * constraint_status is read-only here; submit and reset belong to Module 3.
 */
readonly class StudyProgramService
{
    public function __construct(
        private StudyProgram $studyProgram,
        private Department $department,
        private ReferenceGuard $referenceGuard,
    ) {}

    /**
     * Not paginated: a few dozen rows, used for dropdowns.
     *
     * @return Collection<int, StudyProgramData>
     */
    public function getStudyPrograms(?string $search, ?int $facultyId, ?int $departmentId): Collection
    {
        return StudyProgramData::prepareQuery($this->studyProgram->newQuery())
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")))
            ->when($facultyId, fn (Builder $query) => $query->where('faculty_id', $facultyId))
            ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->orderBy('code')
            ->get()
            ->map(fn (StudyProgram $studyProgram) => StudyProgramData::from($studyProgram));
    }

    public function getStudyProgram(StudyProgram $studyProgram): StudyProgramData
    {
        return StudyProgramData::from(StudyProgramData::loadRelations($studyProgram));
    }

    public function createStudyProgram(StudyProgramFormData $data): StudyProgramData
    {
        $this->ensureDepartmentInFaculty($data);

        $studyProgram = $this->studyProgram->newQuery()->create([
            'faculty_id' => $data->facultyId,
            'department_id' => $data->departmentId,
            'code' => $data->code,
            'name' => $data->name,
        ]);

        return $this->getStudyProgram($studyProgram);
    }

    public function updateStudyProgram(StudyProgram $studyProgram, StudyProgramFormData $data): StudyProgramData
    {
        if ($data->facultyId !== $studyProgram->faculty_id && $this->hasRelations($studyProgram)) {
            throw ApiException::validation(['faculty_id' => [
                __('A study program with lecturers, courses or accounts cannot move to another faculty yet.'),
            ]]);
        }

        $this->ensureDepartmentInFaculty($data);

        $studyProgram->update([
            'faculty_id' => $data->facultyId,
            'department_id' => $data->departmentId,
            'code' => $data->code,
            'name' => $data->name,
        ]);

        return $this->getStudyProgram($studyProgram->fresh());
    }

    /**
     * Soft delete, refused while anything still belongs to the program.
     */
    public function deleteStudyProgram(StudyProgram $studyProgram): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::LECTURERS->value => $studyProgram->lecturers()->count(),
            ReferenceType::COURSES->value => $studyProgram->courses()->count(),
            ReferenceType::USERS->value => $studyProgram->users()->count(),
            ReferenceType::TPB_GROUPS->value => $studyProgram->tpbGroups()->count(),
        ]);

        $studyProgram->delete();
    }

    private function ensureDepartmentInFaculty(StudyProgramFormData $data): void
    {
        if ($data->departmentId === null) {
            return;
        }

        if ($this->department->newQuery()->whereKey($data->departmentId)->value('faculty_id') !== $data->facultyId) {
            throw ApiException::validation(['department_id' => [__('The department must belong to the faculty of the study program.')]]);
        }
    }

    private function hasRelations(StudyProgram $studyProgram): bool
    {
        return $studyProgram->lecturers()->exists()
            || $studyProgram->courses()->exists()
            || $studyProgram->users()->exists();
    }
}
