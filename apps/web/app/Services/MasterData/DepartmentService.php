<?php

namespace App\Services\MasterData;

use App\Data\MasterData\DepartmentData;
use App\Data\MasterData\DepartmentFormData;
use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

readonly class DepartmentService
{
    public function __construct(
        private Department $department,
        private ReferenceGuard $referenceGuard,
    ) {}

    /**
     * Not paginated: a few rows per faculty, used for dropdowns.
     *
     * @return Collection<int, DepartmentData>
     */
    public function getDepartments(?string $search, ?int $facultyId): Collection
    {
        return DepartmentData::prepareQuery($this->department->newQuery())
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")))
            ->when($facultyId, fn (Builder $query) => $query->where('faculty_id', $facultyId))
            ->orderBy('code')
            ->get()
            ->map(fn (Department $department) => DepartmentData::from($department));
    }

    public function getDepartment(Department $department): DepartmentData
    {
        return DepartmentData::from(DepartmentData::loadRelations($department));
    }

    public function createDepartment(DepartmentFormData $data): DepartmentData
    {
        $department = $this->department->newQuery()->create([
            'faculty_id' => $data->facultyId,
            'code' => $data->code,
            'name' => $data->name,
        ]);

        return $this->getDepartment($department);
    }

    public function updateDepartment(Department $department, DepartmentFormData $data): DepartmentData
    {
        if ($data->facultyId !== $department->faculty_id && $department->studyPrograms()->exists()) {
            throw ApiException::validation(['faculty_id' => [
                __('A department with study programs cannot move to another faculty.'),
            ]]);
        }

        $department->update([
            'faculty_id' => $data->facultyId,
            'code' => $data->code,
            'name' => $data->name,
        ]);

        return $this->getDepartment($department->fresh());
    }

    /**
     * Soft delete, refused while study programs still belong to the department.
     */
    public function deleteDepartment(Department $department): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::STUDY_PROGRAMS->value => $department->studyPrograms()->count(),
        ]);

        $department->delete();
    }
}
