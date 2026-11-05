<?php

namespace App\Services\MasterData;

use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Rules\MasterData\CourseRules;
use App\Services\MasterData\CourseImport\CourseSheetReader;
use App\Services\MasterData\CourseImport\ImportIssues;
use App\Services\MasterData\CourseImport\LecturerMatcher;
use App\Services\MasterData\CourseImport\LecturerNameNormalizer;
use App\Services\Shared\ChangeRecorder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

readonly class CourseImportService
{
    private const COURSE_FIELDS = ['name', 'sks', 'semester', 'class_capacity'];

    private const ENRICHMENT_CLASS = 'X';

    public function __construct(
        private CourseSheetReader $reader,
        private DatabaseManager $db,
        private ChangeRecorder $changes,
    ) {}

    /**
     * @param  array<string, int>  $resolutions  normalized lecturer name => lecturer_id
     * @return array<string, mixed>
     */
    public function preview(StudyProgram $studyProgram, string $path, array $resolutions): array
    {
        return Arr::except($this->plan($studyProgram, $this->reader->read($path), $resolutions), 'operations');
    }

    /**
     * @param  array<string, int>  $resolutions
     * @return array{committed: bool, preview: ?array<string, mixed>, result: ?array<string, int>}
     */
    public function commit(StudyProgram $studyProgram, string $path, array $resolutions, string $planHash): array
    {
        $rows = $this->reader->read($path);

        return $this->db->transaction(function () use ($studyProgram, $rows, $resolutions, $planHash) {
            $plan = $this->plan($studyProgram, $rows, $resolutions);

            if (! $plan['can_commit']) {
                return ['committed' => false, 'preview' => Arr::except($plan, 'operations'), 'result' => null];
            }

            if (! hash_equals($plan['plan_hash'], $planHash)) {
                throw ApiException::importPlanChanged();
            }

            foreach ($plan['operations'] as $operation) {
                $this->write($studyProgram, $operation);
            }

            $summary = $plan['summary'];

            return ['committed' => true, 'preview' => null, 'result' => [
                'created' => $summary['created'],
                'updated' => $summary['updated'],
                'unchanged' => $summary['unchanged'],
            ]];
        });
    }

    /**
     * @param  list<array{row: int, values: array<string, int|string|null>}>  $rows
     * @param  array<string, int>  $resolutions
     * @return array<string, mixed>
     */
    private function plan(StudyProgram $studyProgram, array $rows, array $resolutions): array
    {
        $issues = new ImportIssues;
        $parsed = $this->validateRows($rows, $issues);
        $matcher = new LecturerMatcher($studyProgram);
        [$lecturerMatches, $lecturersByKey] = $this->matchLecturers($parsed, $matcher, $resolutions, $issues);

        $groups = $parsed->filter(fn (array $row) => ! in_array('code', $row['invalid'], true))->groupBy(fn (array $row) => $row['values']['code']);
        $existing = Course::query()
            ->with(['studyProgram:id,code', 'courseLecturers.lecturer:id,name,title,study_program_id', 'courseLecturers.lecturer.studyProgram:id,code'])
            ->whereIn('code', $groups->keys())
            ->get()
            ->keyBy('code');

        $courses = [];
        $operations = [];
        foreach ($groups as $code => $group) {
            [$course, $operation] = $this->planCourse($studyProgram, (string) $code, $group->values(), $existing->get($code), $lecturersByKey, $issues);
            $courses[] = $course;
            if ($operation !== null) {
                $operations[] = $operation;
            }
        }

        foreach ($courses as $index => $course) {
            if ($issues->hasErrorIn($course['rows'])) {
                $courses[$index]['status'] = 'invalid';
            }
        }

        $untouched = Course::query()
            ->where('study_program_id', $studyProgram->id)
            ->whereNotIn('code', $groups->keys())
            ->orderBy('semester')
            ->orderBy('code')
            ->get(['code', 'name', 'semester'])
            ->map(fn (Course $course) => ['code' => $course->code, 'name' => $course->name, 'semester' => $course->semester])
            ->all();

        $statuses = collect($courses)->countBy('status');
        $canCommit = $issues->errors() === [];
        $hasUnmatched = collect($lecturerMatches)->contains(fn (array $match) => in_array($match['status'], ['ambiguous', 'not_found'], true));

        return [
            'can_commit' => $canCommit,
            'plan_hash' => 'sha256:'.hash('sha256', (string) json_encode([$studyProgram->id, $courses, array_column($untouched, 'code')])),
            'summary' => [
                'rows' => count($rows),
                'courses' => count($courses),
                'created' => $statuses->get('created', 0),
                'updated' => $statuses->get('updated', 0),
                'unchanged' => $statuses->get('unchanged', 0),
                'invalid' => $statuses->get('invalid', 0),
                'errors' => count($issues->errors()),
                'warnings' => count($issues->warnings()),
            ],
            'errors' => $issues->errors(),
            'warnings' => $issues->warnings(),
            'courses' => $courses,
            'lecturer_matches' => $lecturerMatches,
            'lecturer_options' => $hasUnmatched ? $this->lecturerOptions() : [],
            'untouched_courses' => $untouched,
            'operations' => $canCommit ? $operations : [],
        ];
    }

    /**
     * @param  list<array{row: int, values: array<string, int|string|null>}>  $rows
     * @return Collection<int, array{row: int, values: array<string, int|string|null>, invalid: list<string>}>
     */
    private function validateRows(array $rows, ImportIssues $issues): Collection
    {
        $rules = [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            ...Arr::except(CourseRules::attributes(presence: ['required']), 'parallel_class_count'),
            'class' => ['required', 'string', 'regex:/^[A-X]$/'],
            'lecturer' => ['nullable', 'string', 'max:200'],
        ];
        $messages = [
            'code.regex' => __('Course code may only contain letters and digits.'),
            'sks.in' => __('SKS must be 2, 3 or 4.'),
            'class.regex' => __('Class must be one letter A–W, or X for an enrichment class.'),
        ];

        return collect($rows)->map(function (array $row) use ($rules, $messages, $issues) {
            $values = $row['values'];
            foreach (['code', 'class'] as $field) {
                if (is_string($values[$field])) {
                    $values[$field] = mb_strtoupper(str_replace(' ', '', $values[$field]));
                }
            }

            $validator = Validator::make($values, $rules, $messages, CourseSheetReader::LABELS);
            foreach ($validator->errors()->messages() as $field => $fieldMessages) {
                $issues->error($row['row'], $field, $values[$field] ?? null, $fieldMessages[0]);
            }

            $invalid = array_keys($validator->errors()->messages());
            foreach (['sks', 'semester', 'class_capacity'] as $field) {
                if (! in_array($field, $invalid, true)) {
                    $values[$field] = (int) $values[$field];
                }
            }

            return ['row' => $row['row'], 'values' => $values, 'invalid' => $invalid];
        });
    }

    /**
     * @param  Collection<int, array{row: int, values: array<string, int|string|null>, invalid: list<string>}>  $parsed
     * @param  array<string, int>  $resolutions
     * @return array{0: list<array<string, mixed>>, 1: array<string, Lecturer>}
     */
    private function matchLecturers(Collection $parsed, LecturerMatcher $matcher, array $resolutions, ImportIssues $issues): array
    {
        $byKey = $parsed
            ->filter(fn (array $row) => is_string($row['values']['lecturer']) && ! in_array('lecturer', $row['invalid'], true))
            ->groupBy(fn (array $row) => LecturerNameNormalizer::normalize($row['values']['lecturer']));

        $matches = [];
        $lecturers = [];
        foreach ($byKey as $key => $rows) {
            $key = (string) $key;
            $input = $rows->first()['values']['lecturer'];
            $rowNumbers = $rows->pluck('row')->all();

            if ($key === '') {
                foreach ($rows as $row) {
                    $issues->error($row['row'], 'lecturer', $input, __('The lecturer name cannot be read.'));
                }

                continue;
            }

            if (isset($resolutions[$key])) {
                $lecturer = $matcher->find($resolutions[$key]);
                $match = $lecturer === null
                    ? ['status' => 'not_found', 'lecturer' => null, 'candidates' => []]
                    : ['status' => 'resolved', 'lecturer' => $lecturer, 'candidates' => []];
            } else {
                $match = $matcher->match($key);
            }

            if ($match['lecturer'] !== null) {
                $lecturers[$key] = $match['lecturer'];
                if ($matcher->isOtherFaculty($match['lecturer'])) {
                    $issues->warning($rowNumbers[0], 'lecturer', __('Lecturer :name is from another faculty.', ['name' => $match['lecturer']->name]));
                }
            } else {
                $message = $match['status'] === 'ambiguous'
                    ? __('The name ":name" matches :count lecturers. Choose one in the lecturer list.', ['name' => $input, 'count' => count($match['candidates'])])
                    : __('Lecturer ":name" is not registered. Choose a lecturer in the lecturer list, or add the lecturer first.', ['name' => $input]);
                foreach ($rows as $row) {
                    $issues->error($row['row'], 'lecturer', $row['values']['lecturer'], $message);
                }
            }

            $matches[] = [
                'input' => $input,
                'key' => $key,
                'rows' => $rowNumbers,
                'status' => $match['status'],
                'lecturer' => $match['lecturer'] ? $this->lecturerSummary($match['lecturer']) : null,
                'candidates' => array_map(fn (Lecturer $lecturer) => $this->lecturerSummary($lecturer), $match['candidates']),
            ];
        }

        return [$matches, $lecturers];
    }

    /**
     * @param  Collection<int, array{row: int, values: array<string, int|string|null>, invalid: list<string>}>  $group
     * @param  array<string, Lecturer>  $lecturersByKey
     * @return array{0: array<string, mixed>, 1: ?array<string, mixed>}
     */
    private function planCourse(StudyProgram $studyProgram, string $code, Collection $group, ?Course $course, array $lecturersByKey, ImportIssues $issues): array
    {
        $first = $group->first();
        $rowNumbers = $group->pluck('row')->all();

        $this->checkSameCourseFields($group, $issues);
        $classes = $this->classNumbers($group, $issues);

        if ($course !== null && ($course->is_tpb || $course->study_program_id !== $studyProgram->id)) {
            $issues->error($first['row'], 'code', $code, __('Course code :code is already used by :owner.', [
                'code' => $code,
                'owner' => $course->is_tpb ? __('a TPB course') : $course->studyProgram->code,
            ]));
            $course = null;
        }

        if (! str_starts_with($code, (string) $studyProgram->code)) {
            $issues->warning($first['row'], 'code', __('Course code :code does not start with the study program code :prefix.', ['code' => $code, 'prefix' => $studyProgram->code]));
        }

        $fields = [
            ...Arr::only($first['values'], self::COURSE_FIELDS),
            'parallel_class_count' => $group->count(),
        ];

        // class number => lecturer of that row (null = no lecturer yet)
        $lecturerOf = [];
        foreach ($group as $index => $row) {
            $lecturerName = $row['values']['lecturer'];
            $lecturerOf[$classes[$index] ?? 0] = is_string($lecturerName) ? ($lecturersByKey[LecturerNameNormalizer::normalize($lecturerName)] ?? null) : null;
        }

        $changes = [];
        $classRows = [];
        $oldCount = $course?->parallel_class_count ?? 0;
        $oldLecturers = $course?->courseLecturers->keyBy('class_number') ?? collect();
        $rowOf = collect($classes)->mapWithKeys(fn (int $number, int $index) => [$number => $group[$index]['row']]);

        if ($course !== null) {
            foreach ($fields as $field => $value) {
                if ($course->{$field} !== $value) {
                    $changes[] = ['field' => $field, 'from' => $course->{$field}, 'to' => $value];
                }
            }
            $this->checkDroppedClasses($course, $group->count(), $first['row'], $issues);
        }

        for ($number = 1; $number <= max($group->count(), $oldCount); $number++) {
            $old = $oldLecturers->get($number)?->lecturer;
            $new = $lecturerOf[$number] ?? null;
            $change = match (true) {
                $course === null => 'added',
                $number > $group->count() => 'removed',
                $number > $oldCount => 'added',
                $old?->id !== $new?->id => 'lecturer_changed',
                default => 'same',
            };

            $classRows[] = [
                'number' => $number,
                'label' => $group->count() === 1 && $group[0]['values']['class'] === self::ENRICHMENT_CLASS ? self::ENRICHMENT_CLASS : CourseLecturer::labelFor($number),
                'row' => $rowOf->get($number),
                'lecturer' => $change === 'removed' ? null : ($new ? $this->lecturerSummary($new) : null),
                'previous_lecturer' => $course !== null && $change !== 'same' && $old ? $this->lecturerSummary($old) : null,
                'change' => $change,
            ];
        }

        $status = match (true) {
            $course === null => 'created',
            $changes !== [] || collect($classRows)->contains(fn (array $class) => $class['change'] !== 'same') => 'updated',
            default => 'unchanged',
        };

        $summary = [
            'code' => $code,
            'status' => $status,
            'rows' => $rowNumbers,
            'fields' => $fields,
            'changes' => $changes,
            'classes' => $classRows,
        ];

        $operation = $status === 'unchanged' ? null : [
            'course' => $course,
            'attributes' => ['code' => $code, ...$fields],
            'lecturers' => collect($lecturerOf)->map(fn (?Lecturer $lecturer) => $lecturer?->id)->all(),
        ];

        return [$summary, $operation];
    }

    /**
     * @param  Collection<int, array{row: int, values: array<string, int|string|null>, invalid: list<string>}>  $group
     */
    private function checkSameCourseFields(Collection $group, ImportIssues $issues): void
    {
        $first = $group->first();

        foreach ($group->slice(1) as $row) {
            foreach (self::COURSE_FIELDS as $field) {
                if (in_array($field, $row['invalid'], true) || in_array($field, $first['invalid'], true)) {
                    continue;
                }

                if ($row['values'][$field] !== $first['values'][$field]) {
                    $issues->error($row['row'], $field, $row['values'][$field], __('Differs from row :row for the same course code.', ['row' => $first['row']]));
                }
            }
        }
    }

    /**
     * @param  Collection<int, array{row: int, values: array<string, int|string|null>, invalid: list<string>}>  $group
     * @return array<int, int> group index => class number
     */
    private function classNumbers(Collection $group, ImportIssues $issues): array
    {
        $numbers = [];
        foreach ($group as $index => $row) {
            if (in_array('class', $row['invalid'], true)) {
                continue;
            }

            $label = $row['values']['class'];
            if ($label === self::ENRICHMENT_CLASS) {
                if ($group->count() > 1) {
                    $issues->error($row['row'], 'class', $label, __('Class X counts as class A, so a course with class X can only have that one class.'));

                    continue;
                }
                $issues->warning($row['row'], 'class', __('Enrichment class X is saved as class A.'));
                $numbers[$index] = 1;

                continue;
            }

            $numbers[$index] = ord((string) $label) - 64;
        }

        $seen = [];
        foreach ($numbers as $index => $number) {
            $row = $group[$index];
            if (isset($seen[$number])) {
                $issues->error($row['row'], 'class', $row['values']['class'], __('Class :class appears more than once.', ['class' => $row['values']['class']]));
            } elseif ($number > $group->count()) {
                $issues->error($row['row'], 'class', $row['values']['class'], __('Classes must run from A without gaps (A, B, C, ...).'));
            }
            $seen[$number] = true;
        }

        return $numbers;
    }

    private function checkDroppedClasses(Course $course, int $newCount, int $row, ImportIssues $issues): void
    {
        if ($newCount >= $course->parallel_class_count) {
            return;
        }

        $scheduled = $course->scheduleDetails()
            ->where('class_number', '>', $newCount)
            ->whereHas('schedulingRun', fn (Builder $query) => $query->active())
            ->distinct()
            ->orderBy('class_number')
            ->pluck('class_number');

        if ($scheduled->isNotEmpty()) {
            $issues->error($row, 'class', null, __('Classes :classes are still used by an active schedule.', [
                'classes' => $scheduled->map(fn (int $number) => CourseLecturer::labelFor($number))->implode(', '),
            ]));
        }
    }

    /**
     * @param  array{course: ?Course, attributes: array<string, mixed>, lecturers: array<int, ?int>}  $operation
     */
    private function write(StudyProgram $studyProgram, array $operation): void
    {
        $course = $operation['course'] ?? new Course(['study_program_id' => $studyProgram->id, 'is_tpb' => false]);
        $course->fill($operation['attributes'])->save();

        // Query deletes fire no model events.
        $course->courseLecturers()->where('class_number', '>', $operation['attributes']['parallel_class_count'])->delete();
        $this->changes->record($course->courseLecturers()->getRelated()->getTable());

        foreach ($operation['lecturers'] as $number => $lecturerId) {
            if ($lecturerId === null) {
                $course->courseLecturers()->where('class_number', $number)->delete();

                continue;
            }

            $course->courseLecturers()->updateOrCreate(['class_number' => $number], ['lecturer_id' => $lecturerId]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lecturerSummary(Lecturer $lecturer): array
    {
        return [
            'id' => $lecturer->id,
            'name' => $lecturer->name,
            'title' => $lecturer->title,
            'study_program_code' => $lecturer->studyProgram?->code,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lecturerOptions(): array
    {
        return Lecturer::query()
            ->with('studyProgram:id,code')
            ->orderBy('name')
            ->get(['id', 'name', 'title', 'study_program_id'])
            ->map(fn (Lecturer $lecturer) => $this->lecturerSummary($lecturer))
            ->all();
    }
}