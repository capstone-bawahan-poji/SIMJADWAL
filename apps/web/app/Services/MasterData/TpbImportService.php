<?php

namespace App\Services\MasterData;

use App\Data\MasterData\TpbGroupFormData;
use App\Data\MasterData\TpbOfferingFormData;
use App\Enums\MasterData\TpbCohort;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TpbOffering;
use App\Models\Scheduling\ScheduleDetail;
use App\Services\Scheduling\Import\TpbScheduleSheet;
use App\Services\Scheduling\Period;
use App\Services\Scheduling\TpbRunResolver;
use Illuminate\Database\DatabaseManager;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * Imports the TPB offerings of one period from an .xlsx. One row = one class.
 *
 *   No | Kode MK | Mata Kuliah | SKS | Grup | Peserta | Prodi | Semester | Kelas | Kapasitas | Dosen Pengampu
 *      (+ Ruangan | Hari | Sesi | Pertemuan, read by TpbScheduleImportService, ignored here)
 *
 * Rows with the same Kode MK + Grup form one group. Peserta, Prodi and Semester are read from the
 * first row of the group. Dosen Pengampu is the lecturer name (with or without title); a NIP still works.
 * A course that already has groups in the period is replaced by the file (its old groups, classes and
 * lecturers are deleted first), so a filled template can be edited and imported back. The import runs through TpbOfferingService and TpbGroupService, so every
 * rule of the forms applies. A preview runs the same import and rolls it back.
 */
readonly class TpbImportService
{
    public const MAX_ROWS = 2000;

    public const HEADERS = TpbScheduleSheet::HEADERS;

    private const COHORTS = [
        'maba' => TpbCohort::MABA, 'mahasiswa baru' => TpbCohort::MABA,
        'non maba' => TpbCohort::NON_MABA, 'non-maba' => TpbCohort::NON_MABA, 'non mahasiswa baru' => TpbCohort::NON_MABA,
        'campuran' => TpbCohort::CAMPURAN, 'maba & non maba' => TpbCohort::CAMPURAN,
        'mengulang' => TpbCohort::MENGULANG, 'pilihan' => TpbCohort::PILIHAN,
    ];

    public function __construct(
        private DatabaseManager $db,
        private TpbOfferingService $offerings,
        private TpbGroupService $groups,
        private TpbRunResolver $tpbRuns,
    ) {}

    /**
     * @param  bool  $prefill  fill the sheet with the offerings, groups and classes already saved for the period
     */
    public function template(string $period, bool $prefill = false): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('TPB');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->fromArray($prefill ? $this->currentRows($period) : [
            [1, 'KU2512015', 'Matematika 1', 3, 'G1', 'Maba', '01,02,16,17', 1, 'A', 40, '', '', '', '', ''],
            [2, 'KU2512015', 'Matematika 1', 3, 'G1', '', '', '', 'B', 40, '', '', '', '', ''],
            [3, 'KU2512015', 'Matematika 1', 3, 'G2', 'Mengulang', '01,02,03', '', 'C', 40, '', '', '', '', ''],
        ], null, 'A2');
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $book->createSheet()->setTitle('Petunjuk')->fromArray(TpbScheduleSheet::instructions($period), null, 'A1');

        return $book;
    }

    /**
     * One row per meeting of every class of every grouped offering of the period, in the import
     * layout. The place of each meeting comes from the active TPB run, published or not; a class
     * the run does not hold keeps one row with an empty place. This is also the TPB schedule export.
     *
     * @return list<list<int|string|null>>
     */
    private function currentRows(string $period): array
    {
        $cohortNames = [
            TpbCohort::MABA->value => 'Maba', TpbCohort::NON_MABA->value => 'Non Maba', TpbCohort::CAMPURAN->value => 'Campuran',
            TpbCohort::MENGULANG->value => 'Mengulang', TpbCohort::PILIHAN->value => 'Pilihan',
        ];
        $run = $this->tpbRuns->activeAny($period);
        $meetings = $run === null ? collect() : ScheduleDetail::query()
            ->with(['timeSlot', 'room:id,code'])
            ->where('scheduling_run_id', $run->id)
            ->orderBy('meeting_number')
            ->get()
            ->groupBy(fn (ScheduleDetail $d) => "{$d->course_id}:{$d->class_number}");

        $rows = [];
        $offerings = TpbOffering::query()->where('period', $period)
            ->with(['course', 'groups' => fn ($q) => $q->orderBy('code'), 'groups.studyPrograms', 'groups.courseLecturers.lecturer'])
            ->get()->sortBy(fn (TpbOffering $o) => $o->course->code);

        foreach ($offerings as $offering) {
            foreach ($offering->groups as $group) {
                $first = true;
                foreach ($group->courseLecturers->sortBy('class_number')->values() as $class) {
                    // The file holds one semester per group; a group with mixed semesters keeps the first.
                    $semester = $group->cohort->isRegular() ? $group->studyPrograms->first()?->pivot->target_semester : null;
                    $lecturer = $class->lecturer ? ($class->lecturer->title ? "{$class->lecturer->name}, {$class->lecturer->title}" : $class->lecturer->name) : '';
                    $placed = $meetings->get("{$offering->course_id}:{$class->class_number}") ?? collect([null]);
                    foreach ($placed as $detail) {
                        $rows[] = [
                            count($rows) + 1, $offering->course->code, $offering->course->name, $offering->course->sks,
                            $group->code,
                            $first ? $cohortNames[$group->cohort->value] : '',
                            $first ? $group->studyPrograms->sortBy('code')->pluck('code')->implode(',') : '',
                            $first ? ($semester ?? '') : '',
                            CourseLecturer::labelFor($class->class_number),
                            $offering->class_capacity,
                            $lecturer,
                            $detail?->room?->code ?? '',
                            $detail?->timeSlot->day->label() ?? '',
                            $detail?->timeSlot->session ?? '',
                            $detail?->meeting_number ?? '',
                        ];
                        $first = false;
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @return array{committed: bool, offerings: int, groups: int, classes: int, replaced: int, errors: list<array{row: int, message: string}>}
     */
    public function run(string $path, string $period, bool $apply): array
    {
        $rows = $this->read($path);
        $errors = [];
        $counts = ['offerings' => 0, 'groups' => 0, 'classes' => 0, 'replaced' => 0];
        $lecturers = $this->lecturerIndex();

        $this->db->beginTransaction();
        try {
            foreach (collect($rows)->groupBy(fn (array $r) => $r['values']['code'])->map(fn ($group) => $group->values()->all())->all() as $code => $courseRows) {
                try {
                    $this->importCourse((string) $code, $courseRows, $period, $counts, $lecturers);
                } catch (ApiException $e) {
                    foreach ($this->messages($e) as $message) {
                        $errors[] = ['row' => $courseRows[0]['row'], 'message' => "{$code}: {$message}"];
                    }
                }
            }
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $commit = $apply && $errors === [];
        $commit ? $this->db->commit() : $this->db->rollBack();

        return ['committed' => $commit, ...$counts, 'errors' => $errors];
    }

    /**
     * @param  list<array{row: int, values: array<string, mixed>}>  $courseRows
     * @param  array<string, int>  $counts
     * @param  array{full: array<string, list<int>>, name: array<string, list<int>>, nip: array<string, int>}  $lecturers
     */
    private function importCourse(string $code, array $courseRows, string $period, array &$counts, array $lecturers): void
    {
        $first = $courseRows[0]['values'];
        $course = Course::query()->where('code', $code)->first();
        if ($course === null) {
            if ($first['name'] === null || $first['sks'] === null) {
                throw ApiException::validation(['file' => [__('The course is new: Mata Kuliah and SKS are required.')]]);
            }
            $course = Course::query()->create(['study_program_id' => null, 'is_tpb' => true, 'code' => $code,
                'name' => $first['name'], 'sks' => (int) $first['sks'], 'semester' => null, 'parallel_class_count' => null, 'class_capacity' => null]);
        } elseif (! $course->is_tpb) {
            throw ApiException::validation(['file' => [__('The code belongs to a regular course, not a TPB course.')]]);
        }

        $numbers = $this->classNumbers($courseRows);
        $repeated = array_keys(array_filter(array_count_values($numbers), fn (int $count) => $count > 1));
        if ($repeated !== []) {
            throw ApiException::validation(['file' => [__('Class :class appears more than once. Use Pertemuan 2 for the second meeting of a class.', ['class' => CourseLecturer::labelFor($repeated[0])])]]);
        }
        $capacity = (int) collect($courseRows)->max(fn (array $r) => (int) ($r['values']['capacity'] ?? 0));
        $offering = TpbOffering::query()->where('course_id', $course->id)->where('period', $period)->first();
        if ($offering !== null) {
            $existing = $offering->groups()->get();
            $existing->each(fn ($group) => $this->groups->deleteGroup($group));
            $counts['replaced'] += $existing->isNotEmpty() ? 1 : 0;
        }
        $form = new TpbOfferingFormData($course->id, $period, max($numbers), max($capacity, 1));
        if ($offering === null) {
            $offering = TpbOffering::query()->findOrFail($this->offerings->createOffering($form)->id);
        } else {
            $this->offerings->updateOffering($offering, $form);
        }
        $counts['offerings']++;

        $byGroup = [];
        foreach ($courseRows as $i => $row) {
            $byGroup[(string) ($row['values']['group'] ?? 'G1')][] = [...$row, 'number' => $numbers[$i]];
        }
        foreach ($byGroup as $label => $groupRows) {
            $v = $this->groupValues($label, $groupRows);
            $cohort = self::COHORTS[mb_strtolower(trim((string) $v['cohort']))] ?? throw ApiException::validation(['file' => [__('Peserta must be Maba, Non Maba, Campuran, Mengulang, or Pilihan.')]]);
            $participants = $this->participants($cohort, (string) $v['programs'], $v['semester'], $period);
            $group = $this->groups->createGroup(new TpbGroupFormData($offering->id, $label, $cohort, $participants, array_column($groupRows, 'number')));
            $counts['groups']++;
            foreach ($groupRows as $row) {
                $input = $row['values']['lecturer'];
                if ($input === null) {
                    continue;
                }
                CourseLecturer::query()->where('tpb_group_id', $group->id)->where('class_number', $row['number'])->update(['lecturer_id' => $this->findLecturer($input, $lecturers)]);
            }
            $counts['classes'] += count($groupRows);
        }
    }

    /**
     * @return array{full: array<string, list<int>>, name: array<string, list<int>>, nip: array<string, int>}
     */
    private function lecturerIndex(): array
    {
        $index = ['full' => [], 'name' => [], 'nip' => []];
        foreach (Lecturer::query()->get(['id', 'nip', 'name', 'title']) as $lecturer) {
            $index['nip'][(string) $lecturer->nip] = $lecturer->id;
            $index['name'][$this->normalize($lecturer->name)][] = $lecturer->id;
            $index['full'][$this->normalize($lecturer->name.' '.$lecturer->title)][] = $lecturer->id;
        }

        return $index;
    }

    /**
     * Name first (with title, then without), NIP as a fallback for older files.
     *
     * @param  array{full: array<string, list<int>>, name: array<string, list<int>>, nip: array<string, int>}  $index
     */
    private function findLecturer(string $input, array $index): int
    {
        $key = $this->normalize($input);
        $ids = $index['full'][$key] ?? $index['name'][$key] ?? [];
        if (count($ids) === 1) {
            return $ids[0];
        }
        if ($ids === [] && isset($index['nip'][trim($input)])) {
            return $index['nip'][trim($input)];
        }

        throw ApiException::validation(['file' => [$ids === []
            ? __('Lecturer :name not found.', ['name' => $input])
            : __('Lecturer name :name matches more than one lecturer. Write the full name with title.', ['name' => $input])]]);
    }

    /** Lowercase letters and digits only, so punctuation and spacing differences do not matter. */
    private function normalize(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text));
    }

    /**
     * @param  list<array{row: int, values: array<string, mixed>}>  $courseRows
     * @return list<int> class number per row
     */
    private function classNumbers(array $courseRows): array
    {
        $taken = [];
        foreach ($courseRows as $r) {
            if ($r['values']['class'] !== null) {
                $taken[] = $this->letterToNumber((string) $r['values']['class']);
            }
        }
        $next = 1;
        $numbers = [];
        foreach ($courseRows as $r) {
            if ($r['values']['class'] !== null) {
                $numbers[] = $this->letterToNumber((string) $r['values']['class']);

                continue;
            }
            while (in_array($next, $taken, true)) {
                $next++;
            }
            $taken[] = $next;
            $numbers[] = $next;
        }

        return $numbers;
    }

    private function letterToNumber(string $label): int
    {
        return array_reduce(str_split(strtoupper(trim($label))), fn (int $n, string $c) => $n * 26 + (ord($c) - 64), 0);
    }

    /**
     * @return array<int, int|null> study_program_id => target semester
     */
    private function participants(TpbCohort $cohort, string $programs, mixed $semester, string $period): array
    {
        if (! $cohort->hasPrograms()) {
            return [];
        }
        $first = Period::isOdd($period) ? 1 : 2;
        $target = $cohort->isRegular()
            ? ($semester !== null ? (int) $semester : ($cohort === TpbCohort::NON_MABA ? $first + 2 : $first))
            : null;

        $result = [];
        foreach (array_filter(array_map('trim', preg_split('/[,\-;\s]+/', $programs) ?: [])) as $code) {
            $program = StudyProgram::query()->where('code', $code)->orWhere('code', ltrim($code, '0'))->first()
                ?? throw ApiException::validation(['file' => [__('Study program code :code not found.', ['code' => $code])]]);
            $result[$program->id] = $target;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function messages(ApiException $e): array
    {
        $errors = $e->errors ?? [];

        return $errors === [] ? [$e->getMessage()] : array_values(array_merge(...array_values($errors)));
    }

    /**
     * Peserta, Prodi and Semester of a group: the first filled cell of its rows. Filled cells that
     * disagree are an error, so one group cannot get two kinds or two program lists.
     *
     * @param  list<array{row: int, values: array<string, mixed>}>  $groupRows
     * @return array<string, mixed>
     */
    private function groupValues(string $label, array $groupRows): array
    {
        $values = $groupRows[0]['values'];
        foreach (['cohort', 'programs', 'semester'] as $field) {
            $filled = collect($groupRows)->pluck("values.{$field}")->filter(fn ($v) => $v !== null)->values();
            if ($filled->map(fn ($v) => mb_strtolower(preg_replace('/\s+/u', '', (string) $v)))->unique()->count() > 1) {
                throw ApiException::validation(['file' => [__('Peserta, Prodi, and Semester must be the same on every row of group :group.', ['group' => $label])]]);
            }
            $values[$field] = $filled->first();
        }

        return $values;
    }

    /**
     * A class that appears on more rows (MK 4 SKS: Pertemuan 2) is one class for the data: the
     * repeated rows carry another place, so they are dropped here and read by the schedule import.
     * A repeated row without Pertemuan, Ruangan, Hari or Sesi stays and fails as a duplicate class.
     *
     * @return list<array{row: int, values: array<string, mixed>}>
     */
    private function read(string $path): array
    {
        $rows = [];
        $seen = [];
        foreach ((new TpbScheduleSheet)->read($path, ['code']) as $row) {
            $v = $row['values'];
            if ($v['code'] === null) {
                continue;
            }
            $meeting = TpbScheduleSheet::meetingNumber($v['meeting']);
            $hasPlace = $meeting !== null || $v['room'] !== null || $v['day'] !== null || $v['session'] !== null;
            if ($v['class'] !== null) {
                $key = mb_strtoupper($v['code']).'|'.mb_strtoupper($v['group'] ?? 'G1').'|'.mb_strtoupper($v['class']);
                if (isset($seen[$key]) && $hasPlace) {
                    continue;
                }
                $seen[$key] = true;
            }
            if (($meeting ?? 1) > 1) {
                continue;
            }
            $rows[] = $row;
        }

        if ($rows === []) {
            throw ApiException::validation(['file' => [__('The file has no course rows.')]]);
        }

        return $rows;
    }
}
