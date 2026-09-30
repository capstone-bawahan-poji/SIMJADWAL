<?php

namespace App\Services\MasterData;

use App\Data\MasterData\StudyProgramData;
use App\Data\MasterData\StudyProgramFormData;
use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
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
        private ReferenceGuard $referenceGuard,
    ) {}

    /**
     * Not paginated: a few dozen rows, used for dropdowns.
     *
     * @return Collection<int, StudyProgramData>
     */
    public function getStudyPrograms(?string $search, ?int $facultyId): Collection
    {
        return StudyProgramData::prepareQuery($this->studyProgram->newQuery())
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")))
            ->when($facultyId, fn (Builder $query) => $query->where('faculty_id', $facultyId))
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
        $studyProgram = $this->studyProgram->newQuery()->create([
            'faculty_id' => $data->facultyId,
            'code' => $data->code,
            'name' => $data->name,
        ]);

        return $this->getStudyProgram($studyProgram);
    }

    public function updateStudyProgram(StudyProgram $studyProgram, StudyProgramFormData $data): StudyProgramData
    {
        // MVP: a program that already owns data stays in its faculty.
        if ($data->facultyId !== $studyProgram->faculty_id && $this->hasRelations($studyProgram)) {
            throw ApiException::validation(['faculty_id' => [
                __('A study program with lecturers, courses or accounts cannot move to another faculty yet.'),
            ]]);
        }

        $studyProgram->update([
            'faculty_id' => $data->facultyId,
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

    private function hasRelations(StudyProgram $studyProgram): bool
    {
        return $studyProgram->lecturers()->exists()
            || $studyProgram->courses()->exists()
            || $studyProgram->users()->exists();
    }
}
