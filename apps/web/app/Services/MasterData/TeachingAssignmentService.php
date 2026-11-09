<?php

namespace App\Services\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Data\MasterData\TeachingAssignmentData;
use App\Data\MasterData\TeachingAssignmentFormData;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Teaching assignments (course_lecturers): one lecturer per class of a course.
 * The lecturer may come from any faculty. Rows are hard deleted: schedules keep their own
 * snapshot of course, class and lecturer, so nothing else points at an assignment.
 *
 * TPB classes are created and deleted by their group (TpbGroupService). Here they only get,
 * change or lose their lecturer.
 */
readonly class TeachingAssignmentService
{
    public function __construct(
        private CourseLecturer $courseLecturer,
        private ResolvesOrgScope $orgScope,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TeachingAssignmentData>
     */
    public function getAssignments(
        User $actor,
        ?int $courseId,
        ?int $lecturerId,
        ?int $studyProgramId,
        ?int $tpbOfferingId,
        ?int $tpbGroupId,
        int $perPage,
        ?string $tpbPeriod = null,
    ): LengthAwarePaginator {
        return TeachingAssignmentData::prepareQuery($this->courseLecturer->newQuery())
            ->unless($this->orgScope->readsAllFaculties($actor), fn (Builder $query) => $query->whereHas('course', fn (Builder $query) => $query
                ->where('is_tpb', true)
                ->orWhere(fn (Builder $query) => $this->orgScope->applyStudyProgramScope($query, $actor))))
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->when($lecturerId, fn (Builder $query) => $query->where('lecturer_id', $lecturerId))
            ->when($studyProgramId, fn (Builder $query) => $query->whereRelation('course', 'study_program_id', $studyProgramId))
            ->when($tpbOfferingId, fn (Builder $query) => $query->where('tpb_offering_id', $tpbOfferingId))
            ->when($tpbGroupId, fn (Builder $query) => $query->where('tpb_group_id', $tpbGroupId))
            ->when($tpbPeriod, fn (Builder $query) => $query->whereRelation('tpbOffering', 'period', $tpbPeriod))
            ->orderBy('course_id')
            ->orderBy('class_number')
            ->paginate($perPage)
            ->through(fn (CourseLecturer $assignment) => TeachingAssignmentData::from($assignment));
    }

    public function getAssignment(CourseLecturer $assignment): TeachingAssignmentData
    {
        return TeachingAssignmentData::from(TeachingAssignmentData::loadRelations($assignment));
    }

    public function createAssignment(Course $course, TeachingAssignmentFormData $data): TeachingAssignmentData
    {
        if ($course->is_tpb) {
            throw ApiException::validation(['course_id' => [__('TPB classes are created from their group. Pick the lecturer on the class instead.')]]);
        }
        $this->ensureValid($course, $data);

        $assignment = $this->courseLecturer->newQuery()->create($this->attributes($data));

        return $this->getAssignment($assignment);
    }

    /**
     * The course never changes: UpdateTeachingAssignmentRequest prohibits course_id.
     */
    public function updateAssignment(CourseLecturer $assignment, TeachingAssignmentFormData $data): TeachingAssignmentData
    {
        if ($assignment->tpb_offering_id !== null) {
            $assignment->update(['lecturer_id' => $data->lecturerId]);

            return $this->getAssignment($assignment->fresh());
        }

        $this->ensureValid($assignment->course, $data);
        $assignment->update($this->attributes($data));

        return $this->getAssignment($assignment->fresh());
    }

    public function deleteAssignment(CourseLecturer $assignment): void
    {
        if ($assignment->tpb_offering_id !== null) {
            throw ApiException::validation(['id' => [__('A TPB class leaves by removing it from its group. To clear its lecturer, set the lecturer to empty.')]]);
        }

        $assignment->delete();
    }

    private function ensureValid(Course $course, TeachingAssignmentFormData $data): void
    {
        if ($data->classNumber > $course->parallel_class_count) {
            throw ApiException::validation(['class_number' => [__('The course only has :count classes.', ['count' => $course->parallel_class_count])]]);
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function attributes(TeachingAssignmentFormData $data): array
    {
        return [
            'course_id' => $data->courseId,
            'class_number' => $data->classNumber,
            'lecturer_id' => $data->lecturerId,
        ];
    }
}
