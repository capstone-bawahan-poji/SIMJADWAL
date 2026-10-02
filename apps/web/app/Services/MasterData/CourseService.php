<?php

namespace App\Services\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Data\MasterData\CourseData;
use App\Data\MasterData\CourseFormData;
use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Regular courses are listed within the reader's scope. TPB courses are visible to every reader.
 */
readonly class CourseService
{
    public function __construct(
        private Course $course,
        private ResolvesOrgScope $orgScope,
        private ReferenceGuard $referenceGuard,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CourseData>
     */
    public function getCourses(
        User $actor,
        ?string $search,
        ?bool $isTpb,
        ?int $facultyId,
        ?int $studyProgramId,
        ?int $semester,
        int $perPage,
    ): LengthAwarePaginator {
        return CourseData::prepareQuery($this->course->newQuery())
            ->unless($this->orgScope->readsAllFaculties($actor), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('is_tpb', true)
                ->orWhere(fn (Builder $query) => $this->orgScope->applyStudyProgramScope($query, $actor))))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")))
            ->when($isTpb !== null, fn (Builder $query) => $query->where('is_tpb', $isTpb))
            ->when($facultyId, fn (Builder $query) => $query->whereRelation('studyProgram', 'faculty_id', $facultyId))
            ->when($studyProgramId, fn (Builder $query) => $query->where('study_program_id', $studyProgramId))
            ->when($semester, fn (Builder $query) => $query->where('semester', $semester))
            ->orderBy('semester')
            ->orderBy('code')
            ->paginate($perPage)
            ->through(fn (Course $course) => CourseData::from($course));
    }

    public function getCourse(Course $course): CourseData
    {
        return CourseData::from(CourseData::loadRelations($course));
    }

    public function createCourse(CourseFormData $data): CourseData
    {
        $course = $this->course->newQuery()->create($this->attributes($data));

        return $this->getCourse($course);
    }

    /**
     * Owner (study_program_id, is_tpb) never changes: UpdateCourseRequest prohibits it.
     */
    public function updateCourse(Course $course, CourseFormData $data): CourseData
    {
        $this->ensureMappedClassesKept($course, $data->parallelClassCount);

        $course->update($this->attributes($data));

        return $this->getCourse($course->fresh());
    }

    /**
     * Soft delete.
     */
    public function deleteCourse(Course $course): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::TEACHING_ASSIGNMENTS->value => $course->courseLecturers()->count(),
            ReferenceType::TPB_GROUPS->value => $course->tpbGroups()->count(),
            ReferenceType::SCHEDULES->value => $this->referenceGuard->activeScheduleCount($course->scheduleDetails()),
        ]);

        $course->delete();
    }

    /**
     * Lowering the class count may not drop a class that already has a lecturer.
     */
    private function ensureMappedClassesKept(Course $course, int $parallelClassCount): void
    {
        $dropped = $course->courseLecturers()
            ->where('class_number', '>', $parallelClassCount)
            ->orderBy('class_number')
            ->pluck('class_number');

        if ($dropped->isNotEmpty()) {
            throw ApiException::validation(['parallel_class_count' => [__('Classes :classes still have a lecturer. Remove those teaching assignments first.', [
                'classes' => $dropped->map(fn (int $number) => CourseLecturer::labelFor($number))->implode(', '),
            ])]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(CourseFormData $data): array
    {
        return [
            'study_program_id' => $data->studyProgramId,
            'is_tpb' => $data->isTpb,
            'code' => $data->code,
            'name' => $data->name,
            'sks' => $data->sks,
            'semester' => $data->semester,
            'parallel_class_count' => $data->parallelClassCount,
            'class_capacity' => $data->classCapacity,
        ];
    }
}
