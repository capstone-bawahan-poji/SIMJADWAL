<?php

namespace App\Services\Constraint;

use App\Contracts\Account\ResolvesOrgScope;
use App\Data\Constraint\ConstraintSubmissionData;
use App\Enums\Constraint\ConstraintCode;
use App\Enums\Constraint\ConstraintStatus;
use App\Exceptions\Shared\ApiException;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Prodi admins submit the constraints of their program. A submit needs every class of every
 * course to have a lecturer. A faculty admin then accepts it or returns it to draft with a note.
 * Submitted and accepted programs are locked (StudyProgram::isLocked()).
 */
readonly class ConstraintSubmissionService
{
    public function __construct(
        private StudyProgram $studyProgram,
        private ResolvesOrgScope $orgScope,
        private ConstraintActivityLogService $activityLog,
        private DatabaseManager $db,
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
     * The summary row plus what the faculty admin reviews: courses still missing lecturers and
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

    public function submit(StudyProgram $studyProgram): ConstraintSubmissionData
    {
        $this->db->transaction(function () use ($studyProgram) {
            $locked = $this->lockRow($studyProgram);

            if ($locked->isLocked()) {
                throw ApiException::constraintsLocked();
            }

            $this->ensureComplete($locked);

            $locked->update([
                'constraint_status' => ConstraintStatus::SUBMITTED,
                'constraint_submitted_at' => now(),
                'constraint_reviewed_by' => null,
                'constraint_reviewed_at' => null,
                'constraint_return_note' => null,
            ]);
            $this->activityLog->constraintsSubmitted($locked);
        });

        return $this->summary($studyProgram);
    }

    public function accept(StudyProgram $studyProgram, User $reviewer): ConstraintSubmissionData
    {
        $this->db->transaction(function () use ($studyProgram, $reviewer) {
            $locked = $this->lockRow($studyProgram);
            $this->ensureStatus($locked, [ConstraintStatus::SUBMITTED], __('Only a submitted program can be accepted.'));

            $locked->update([
                'constraint_status' => ConstraintStatus::ACCEPTED,
                'constraint_reviewed_by' => $reviewer->id,
                'constraint_reviewed_at' => now(),
                'constraint_return_note' => null,
            ]);
            $this->activityLog->constraintsAccepted($locked);
        });

        return $this->summary($studyProgram);
    }

    public function return(StudyProgram $studyProgram, User $reviewer, string $note): ConstraintSubmissionData
    {
        $this->db->transaction(function () use ($studyProgram, $reviewer, $note) {
            $locked = $this->lockRow($studyProgram);
            $this->ensureStatus($locked, [ConstraintStatus::SUBMITTED, ConstraintStatus::ACCEPTED], __('Only a submitted or accepted program can be returned.'));

            $locked->update([
                'constraint_status' => ConstraintStatus::DRAFT,
                'constraint_submitted_at' => null,
                'constraint_reviewed_by' => $reviewer->id,
                'constraint_reviewed_at' => now(),
                'constraint_return_note' => $note,
            ]);
            $this->activityLog->constraintsReturned($locked);
        });

        return $this->summary($studyProgram);
    }

    /**
     * Fresh copy of the program, row-locked until the surrounding transaction ends, so two
     * concurrent submit / review calls cannot both pass the status check.
     */
    private function lockRow(StudyProgram $studyProgram): StudyProgram
    {
        return $this->studyProgram->newQuery()->lockForUpdate()->findOrFail($studyProgram->id);
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

    private function summary(StudyProgram $studyProgram): ConstraintSubmissionData
    {
        return ConstraintSubmissionData::from($this->query()->findOrFail($studyProgram->id));
    }

    /**
     * @param  list<ConstraintStatus>  $allowed
     */
    private function ensureStatus(StudyProgram $studyProgram, array $allowed, string $message): void
    {
        if (! in_array($studyProgram->constraint_status, $allowed, true)) {
            throw ApiException::invalidState($message);
        }
    }

    private function ensureComplete(StudyProgram $studyProgram): void
    {
        $errors = [];

        if (! $studyProgram->courses()->exists()) {
            $errors['courses'][] = __('The study program has no courses yet.');
        }

        foreach ($this->incompleteCourses($studyProgram) as $course) {
            $errors['courses'][] = __('Course :code has :assigned of :total classes with a lecturer.', [
                'code' => $course->code,
                'assigned' => $course->course_lecturers_count,
                'total' => $course->parallel_class_count,
            ]);
        }

        if ($errors !== []) {
            throw ApiException::validation($errors);
        }
    }

    /**
     * Single definition of an incomplete course: fewer lecturers than parallel classes.
     *
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    private function incompleteScope(Builder $query): Builder
    {
        return $query->whereRaw('(select count(*) from course_lecturers where course_lecturers.course_id = courses.id) < courses.parallel_class_count');
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
