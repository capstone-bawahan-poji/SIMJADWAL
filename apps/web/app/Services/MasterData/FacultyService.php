<?php

namespace App\Services\MasterData;

use App\Data\MasterData\FacultyData;
use App\Data\MasterData\FacultyFormData;
use App\Enums\MasterData\ReferenceType;
use App\Models\MasterData\Faculty;
use Illuminate\Support\Collection;

readonly class FacultyService
{
    public function __construct(
        private Faculty $faculty,
        private ReferenceGuard $referenceGuard,
    ) {}

    /**
     * Not paginated: a handful of rows, used for dropdowns.
     *
     * @return Collection<int, FacultyData>
     */
    public function getFaculties(): Collection
    {
        return FacultyData::prepareQuery($this->faculty->newQuery())
            ->orderBy('code')
            ->get()
            ->map(fn (Faculty $faculty) => FacultyData::from($faculty));
    }

    public function getFaculty(Faculty $faculty): FacultyData
    {
        return FacultyData::from(FacultyData::loadRelations($faculty));
    }

    public function createFaculty(FacultyFormData $data): FacultyData
    {
        $faculty = $this->faculty->newQuery()->create(['code' => $data->code, 'name' => $data->name]);

        return $this->getFaculty($faculty);
    }

    public function updateFaculty(Faculty $faculty, FacultyFormData $data): FacultyData
    {
        $faculty->update(['code' => $data->code, 'name' => $data->name]);

        return $this->getFaculty($faculty->fresh());
    }

    /**
     * Soft delete, refused while anything still belongs to the faculty.
     */
    public function deleteFaculty(Faculty $faculty): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::STUDY_PROGRAMS->value => $faculty->studyPrograms()->count(),
            ReferenceType::ROOMS->value => $faculty->rooms()->count(),
            ReferenceType::USERS->value => $faculty->users()->count(),
            ReferenceType::SCHEDULING_RUNS->value => $faculty->schedulingRuns()->count(),
        ]);

        $faculty->delete();
    }
}
