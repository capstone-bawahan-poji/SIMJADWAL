<?php

namespace App\Services\Constraint;

use App\Contracts\Account\ResolvesOrgScope;
use App\Data\Constraint\ConstraintSubmissionData;
use App\Enums\Constraint\ConstraintCode;
use App\Enums\Constraint\ConstraintStatus;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Summarizes the courses and lecturer preferences of each study program.
 */
readonly class ConstraintSubmissionService
{
    public function __construct(
        private StudyProgram $studyProgram,
        private ResolvesOrgScope $orgScope,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ConstraintSubmissionData>
     */
    public function getSubmissions(User $actor, ?string $search, ?ConstraintStatus $status, ?int $facultyId, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->tap(fn (Builder $query) => $this->scope($query, $actor))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")))
            ->when($status, fn (Builder $query) => $query->where('constraint_status', $status->value))
            ->when($facultyId, fn (Builder $query) => $query->where('faculty_id', $facultyId))
            ->orderBy('code')
            ->paginate($perPage)
            ->through(fn (StudyProgram $studyProgram) => ConstraintSubmissionData::from($studyProgram));
    }

    /**
     * The summary row, courses still missing lecturers and
     * the preference matrix of every lecturer.
     *
     * @return array{
     *     summary: array<string, mixed>,
     *     incomplete_courses: list<array{id: int, code: string, name: string, parallel_class_count: int, assigned_class_count: int}>,
     *     lecturers: list<array{id: int, nip: string, name: string, title: ?string, preferences: list<array{time_slot_id: int, type: 'want'|'avoid'}>}>,
     * }
     */
    public function getSubmission(StudyProgram $studyProgram): array
    {
        $summary = ConstraintSubmissionData::from($this->query()->findOrFail($studyProgram->id));

        $incomplete = $this->incompleteCourses($studyProgram)->map(fn (Course $course) => [
            'id' => $course->id,
            'code' => $course->code,
            'name' => $course->name,
            'parallel_class_count' => $course->parallel_class_count,
            'assigned_class_count' => $course->course_lecturers_count,
        ])->values()->all();

        $lecturers = $studyProgram->lecturers()
            ->with(['preferences.constraintType'])
            ->orderBy('name')
            ->get()
            ->map(fn (Lecturer $lecturer) => [
                'id' => $lecturer->id,
                'nip' => $lecturer->nip,
                'name' => $lecturer->name,
                'title' => $lecturer->title,
                'preferences' => $lecturer->preferences->map(fn (LecturerPreference $row) => [
                    'time_slot_id' => $row->time_slot_id,
                    'type' => $row->constraintType->code === ConstraintCode::SC_INGIN ? 'want' : 'avoid',
                ])->sortBy('time_slot_id')->values()->all(),
            ])->all();

        return ['summary' => $summary->toArray(), 'incomplete_courses' => $incomplete, 'lecturers' => $lecturers];
    }

    /**
     * @return Builder<StudyProgram>
     */
    private function query(): Builder
    {
        return ConstraintSubmissionData::prepareQuery($this->studyProgram->newQuery())
            ->withCount([
                'lecturers',
                'courses',
                'lecturers as lecturers_with_preferences_count' => fn (Builder $query) => $query->has('preferences'),
                'courses as incomplete_courses_count' => fn (Builder $query) => $this->incompleteScope($query),
            ]);
    }

    /**
     * Faculty admins see their faculty, a prodi admin only the own program. Superadmin sees all.
     *
     * @param  Builder<StudyProgram>  $query
     */
    private function scope(Builder $query, User $actor): void
    {
        $this->orgScope->applyStudyProgramScope($query, $actor, 'id');
    }

    public function summary(StudyProgram $studyProgram): ConstraintSubmissionData
    {
        return ConstraintSubmissionData::from($this->query()->findOrFail($studyProgram->id));
    }

    /**
     * Single definition of an incomplete course: an active course with fewer lecturers than parallel classes.
     *
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    private function incompleteScope(Builder $query): Builder
    {
        return $query->where('courses.is_active', true)->whereRaw('(select count(*) from course_lecturers where course_lecturers.course_id = courses.id) < courses.parallel_class_count');
    }

    /**
     * @return Collection<int, Course>
     */
    private function incompleteCourses(StudyProgram $studyProgram): Collection
    {
        return $studyProgram->courses()
            ->tap(fn (Builder $query) => $this->incompleteScope($query))
            ->withCount('courseLecturers')
            ->orderBy('code')
            ->get();
    }
}
