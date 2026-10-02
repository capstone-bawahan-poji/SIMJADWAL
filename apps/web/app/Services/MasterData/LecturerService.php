<?php

namespace App\Services\MasterData;

use App\Data\MasterData\LecturerData;
use App\Data\MasterData\LecturerFormData;
use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Lecturer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lecturers are listed across all faculties: a class may be taught by a lecturer of
 * another faculty, so the list is not limited to the reader's scope.
 */
readonly class LecturerService
{
    public function __construct(
        private Lecturer $lecturer,
        private ReferenceGuard $referenceGuard,
    ) {}

    /**
     * @return LengthAwarePaginator<int, LecturerData>
     */
    public function getLecturers(?string $search, ?int $facultyId, ?int $studyProgramId, int $perPage): LengthAwarePaginator
    {
        return LecturerData::prepareQuery($this->lecturer->newQuery())
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('nip', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")))
            ->when($facultyId, fn (Builder $query) => $query->whereRelation('studyProgram', 'faculty_id', $facultyId))
            ->when($studyProgramId, fn (Builder $query) => $query->where('study_program_id', $studyProgramId))
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (Lecturer $lecturer) => LecturerData::from($lecturer));
    }

    public function getLecturer(Lecturer $lecturer): LecturerData
    {
        return LecturerData::from(LecturerData::loadRelations($lecturer));
    }

    public function createLecturer(LecturerFormData $data): LecturerData
    {
        $lecturer = $this->lecturer->newQuery()->create([
            'study_program_id' => $data->studyProgramId,
            'nip' => $data->nip,
            'name' => $data->name,
            'title' => $data->title,
        ]);

        return $this->getLecturer($lecturer);
    }

    public function updateLecturer(Lecturer $lecturer, LecturerFormData $data): LecturerData
    {
        if ($data->studyProgramId !== $lecturer->study_program_id && $this->hasRelations($lecturer)) {
            throw ApiException::validation(['study_program_id' => [
                __('A lecturer with teaching assignments, preferences or schedules cannot move to another study program yet.'),
            ]]);
        }

        $lecturer->update([
            'study_program_id' => $data->studyProgramId,
            'nip' => $data->nip,
            'name' => $data->name,
            'title' => $data->title,
        ]);

        return $this->getLecturer($lecturer->fresh());
    }

    /**
     * Soft delete. A linked account must be unlinked first (Account module).
     */
    public function deleteLecturer(Lecturer $lecturer): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::TEACHING_ASSIGNMENTS->value => $lecturer->courseLecturers()->count(),
            ReferenceType::LECTURER_PREFERENCES->value => $lecturer->preferences()->count(),
            ReferenceType::SCHEDULES->value => $this->referenceGuard->activeScheduleCount($lecturer->scheduleDetails()),
            ReferenceType::USERS->value => $lecturer->user_id === null ? 0 : 1,
        ]);

        $lecturer->delete();
    }

    private function hasRelations(Lecturer $lecturer): bool
    {
        return $lecturer->courseLecturers()->exists()
            || $lecturer->preferences()->exists()
            || $lecturer->scheduleDetails()->exists();
    }
}
