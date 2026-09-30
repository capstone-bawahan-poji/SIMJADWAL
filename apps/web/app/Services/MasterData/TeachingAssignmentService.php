<?php

namespace App\Services\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Data\MasterData\TeachingAssignmentData;
use App\Data\MasterData\TeachingAssignmentFormData;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TpbGroup;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;

/**
 * Teaching assignments (course_lecturers): one lecturer per class of a course.
 * The lecturer may come from any faculty. Rows are hard deleted: schedules keep their own
 * snapshot of course, class and lecturer, so nothing else points at an assignment.
 */
readonly class TeachingAssignmentService
{
    public function __construct(
        private CourseLecturer $courseLecturer,
        private ResolvesOrgScope $orgScope,
        private TpbClashChecker $clashChecker,
        private DatabaseManager $db,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TeachingAssignmentData>
     */
    public function getAssignments(
        User $actor,
        ?int $courseId,
        ?int $lecturerId,
        ?int $studyProgramId,
        ?int $tpbGroupId,
        int $perPage,
    ): LengthAwarePaginator {
        return TeachingAssignmentData::prepareQuery($this->courseLecturer->newQuery())
            ->unless($this->orgScope->readsAllFaculties($actor), fn (Builder $query) => $query->whereHas('course', fn (Builder $query) => $query
                ->where('is_tpb', true)
                ->orWhere(fn (Builder $query) => $this->orgScope->applyStudyProgramScope($query, $actor))))
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->when($lecturerId, fn (Builder $query) => $query->where('lecturer_id', $lecturerId))
            ->when($studyProgramId, fn (Builder $query) => $query->whereRelation('course', 'study_program_id', $studyProgramId))
            ->when($tpbGroupId, fn (Builder $query) => $query->where('tpb_group_id', $tpbGroupId))
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
        $this->ensureValid($course, $data);

        $assignment = $this->db->transaction(function () use ($data) {
            $assignment = $this->courseLecturer->newQuery()->create($this->attributes($data));
            $this->ensureNoTpbClash($assignment);

            return $assignment;
        });

        return $this->getAssignment($assignment);
    }

    /**
     * The course never changes: UpdateTeachingAssignmentRequest prohibits course_id.
     */
    public function updateAssignment(CourseLecturer $assignment, TeachingAssignmentFormData $data): TeachingAssignmentData
    {
        $this->ensureValid($assignment->course, $data);

        $this->db->transaction(function () use ($assignment, $data) {
            $assignment->update($this->attributes($data));
            $this->ensureNoTpbClash($assignment->fresh());
        });

        return $this->getAssignment($assignment->fresh());
    }

    public function deleteAssignment(CourseLecturer $assignment): void
    {
        $assignment->delete();
    }

    private function ensureValid(Course $course, TeachingAssignmentFormData $data): void
    {
        $errors = [];

        if ($data->classNumber > $course->parallel_class_count) {
            $errors['class_number'][] = __('The course only has :count classes.', ['count' => $course->parallel_class_count]);
        }

        if (! $course->is_tpb) {
            if ($data->tpbGroupId !== null) {
                $errors['tpb_group_id'][] = __('Only TPB classes belong to a TPB group.');
            }
            if ($data->roomId !== null) {
                $errors['room_id'][] = __('Only TPB classes get a room here. Regular classes get theirs from the scheduler.');
            }
        } else {
            if ($data->tpbGroupId === null || ! TpbGroup::query()->whereKey($data->tpbGroupId)->where('course_id', $course->id)->exists()) {
                $errors['tpb_group_id'][] = __('A TPB class needs a TPB group of the same course.');
            }
            if ($data->roomId !== null && Room::query()->whereKey($data->roomId)->whereNotNull('faculty_id')->exists()) {
                $errors['room_id'][] = __('TPB classes may only use shared rooms.');
            }
        }

        if ($errors !== []) {
            throw ApiException::validation($errors);
        }
    }

    private function ensureNoTpbClash(CourseLecturer $assignment): void
    {
        $errors = $this->clashChecker->classErrors($assignment);

        if ($errors !== []) {
            throw ApiException::validation($errors);
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
            'tpb_group_id' => $data->tpbGroupId,
            'room_id' => $data->roomId,
        ];
    }
}
