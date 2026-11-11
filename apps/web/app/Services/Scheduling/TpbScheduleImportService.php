<?php

namespace App\Services\Scheduling;

use App\Enums\Scheduling\RunSource;
use App\Enums\Scheduling\RunStatus;
use App\Models\MasterData\CourseLecturer;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Services\MasterData\TpbImportService;
use App\Services\Scheduling\Import\TpbScheduleSheet;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Throwable;

/**
 * POS 1.2.c: the TPB admin enters the TPB schedule from a file instead of running the GA. One
 * file format for data and schedule (docs/plans/konvensi-file-tpb.md):
 *
 *   - schedule only (Kode MK, Kelas, Ruangan, Hari, Sesi): the TPB offerings, groups and lecturers
 *     of the period must already be in the system (Mapping, or the TPB data import),
 *   - data and schedule (the file also has Grup and Peserta): the data is applied first, in the
 *     same transaction, so one file creates the groups and the run,
 *   - data only (the schedule cells are empty): the data is applied and no run is made.
 *
 * Every schedule row is one meeting of a known class: Kode MK + Kelas + Hari + Sesi + Ruangan, and
 * Pertemuan (1 when empty; MK 4 SKS has 2).
 *
 * The result is an ordinary TPB run (source import), not active. The TPB admin activates and
 * publishes it like a GA run, and faculties then get its rooms, lecturers and program blocks.
 */
readonly class TpbScheduleImportService
{
    public function __construct(
        private DatabaseManager $db,
        private TpbImportService $data,
    ) {}

    /**
     * @return array{committed: bool, run_id: int|null, meetings: int, schedule?: list<array<string, mixed>>, data: array<string, int>|null, errors: list<array{row: int, message: string}>, warnings: list<array{row: int, message: string}>}
     */
    public function run(User $actor, string $period, string $path, bool $apply): array
    {
        $sheet = new TpbScheduleSheet;
        $sheet->read($path, ['code']);
        $withData = $sheet->has('group') && $sheet->has('cohort');
        $sheet->requireColumns($withData ? ['code'] : ['code', 'class', 'day', 'session', 'room']);

        $this->db->beginTransaction();
        try {
            $result = $this->import($actor, $period, $path, $apply, $sheet, $withData);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $result['committed'] ? $this->db->commit() : $this->db->rollBack();

        if ($result['committed'] && $result['run_id'] !== null) {
            activity('scheduling')->causedBy($actor)->event('scheduling_run.imported')
                ->withProperties(['scheduling_run_id' => $result['run_id'], 'meetings' => $result['meetings']])
                ->log(__('Imported the TPB schedule of :period as run :id', ['period' => Period::label($period), 'id' => $result['run_id']]));
        }

        return $result;
    }

    /**
     * Runs inside the caller's transaction. Rolled back by the caller unless committed is true.
     *
     * @return array{committed: bool, run_id: int|null, meetings: int, schedule?: list<array<string, mixed>>, data: array<string, int>|null, errors: list<array{row: int, message: string}>, warnings: list<array{row: int, message: string}>}
     */
    private function import(User $actor, string $period, string $path, bool $apply, TpbScheduleSheet $sheet, bool $withData): array
    {
        $data = null;
        if ($withData) {
            $applied = $this->data->run($path, $period, true);
            $data = ['offerings' => $applied['offerings'], 'groups' => $applied['groups'], 'classes' => $applied['classes'], 'replaced' => $applied['replaced']];
            if ($applied['errors'] !== []) {
                return ['committed' => false, 'run_id' => null, 'meetings' => 0, 'data' => $data, 'errors' => $applied['errors'], 'warnings' => []];
            }
        }

        $classes = CourseLecturer::query()
            ->with(['course:id,code,name,sks', 'tpbGroup:id,code', 'lecturer:id,name'])
            ->whereRelation('tpbOffering', 'period', $period)
            ->get()
            ->keyBy(fn (CourseLecturer $c) => mb_strtoupper($c->course->code).':'.$c->class_number);

        $errors = [];
        $warnings = [];
        $details = [];
        $schedule = [];
        $seen = [];
        $lecturerAt = [];
        $roomAt = [];
        $groupSlots = [];
        foreach ($sheet->read($path, ['code']) as ['row' => $line, 'values' => $v]) {
            // A data file may leave classes unscheduled: their place cells are empty.
            if ($withData && $v['room'] === null && $v['day'] === null && $v['session'] === null) {
                continue;
            }

            $number = TpbScheduleSheet::classNumber($v['class']);
            $class = $number === null ? null : ($classes[mb_strtoupper((string) $v['code']).':'.$number] ?? null);
            $slotId = $sheet->timeSlotId($v['day'], $v['session']);
            $room = $sheet->room($v['room']);

            $message = match (true) {
                $class === null => __('Class :code :class is not a TPB class of this period.', ['code' => $v['code'] ?? '-', 'class' => $v['class'] ?? '-']),
                $class->lecturer_id === null => __('Class :code :class has no lecturer yet.', ['code' => $v['code'], 'class' => $v['class']]),
                $slotId === null => __('Hari and Sesi do not match a time slot.'),
                $room === null => __('Room :room not found.', ['room' => $v['room'] ?? '-']),
                isset($roomAt["{$slotId}:{$room->id}"]) => __('Room :room is already used in this slot (row :other).', ['room' => $room->code, 'other' => $roomAt["{$slotId}:{$room->id}"]]),
                isset($lecturerAt["{$slotId}:{$class->lecturer_id}"]) => __('The lecturer already teaches in this slot (row :other).', ['other' => $lecturerAt["{$slotId}:{$class->lecturer_id}"]]),
                default => null,
            };
            if ($message !== null) {
                $errors[] = ['row' => $line, 'message' => $message];

                continue;
            }

            $key = "{$class->course_id}:{$class->class_number}";
            $meeting = TpbScheduleSheet::meetingNumber($v['meeting']) ?? (count($seen[$key] ?? []) + 1);
            if ($meeting < 1 || $meeting > ($class->course->sks === 4 ? 2 : 1)) {
                $errors[] = ['row' => $line, 'message' => __('Class :code :class has more meetings than its SKS allow.', ['code' => $v['code'], 'class' => $v['class']])];

                continue;
            }
            if (isset($seen[$key][$meeting])) {
                $errors[] = ['row' => $line, 'message' => __('Class :code :class has meeting :meeting twice in the file.', ['code' => $v['code'], 'class' => $v['class'], 'meeting' => $meeting])];

                continue;
            }
            $seen[$key][$meeting] = true;
            $roomAt["{$slotId}:{$room->id}"] = $line;
            $lecturerAt["{$slotId}:{$class->lecturer_id}"] = $line;
            $groupSlots["{$class->tpb_group_id}:{$meeting}"][$slotId] = $class->tpbGroup?->code;
            if ($room->capacity < (int) ($v['capacity'] ?? 0)) {
                $warnings[] = ['row' => $line, 'message' => __('Room :room is smaller than the class.', ['room' => $room->code])];
            }
            $schedule[] = [
                'row' => $line,
                'code' => $class->course->code,
                'course' => $class->course->name,
                'class' => CourseLecturer::labelFor($class->class_number),
                'group' => $class->tpbGroup?->code,
                'meeting' => $meeting,
                'day' => $v['day'],
                'session' => $v['session'],
                'room' => $room->code,
                'lecturer' => $class->lecturer->name ?? null,
            ];
            $details[] = [
                'course_id' => $class->course_id,
                'class_number' => $class->class_number,
                'meeting_number' => $meeting,
                'lecturer_id' => $class->lecturer_id,
                'time_slot_id' => $slotId,
                'room_id' => $room->id,
            ];
        }

        // A file without any schedule cell only carries data.
        if ($details !== [] || $errors !== []) {
            foreach ($classes as $class) {
                $expected = $class->course->sks === 4 ? 2 : 1;
                $found = count($seen["{$class->course_id}:{$class->class_number}"] ?? []);
                if ($found < $expected) {
                    $warnings[] = ['row' => 0, 'message' => __('Class :code :class has :found of :expected meetings in the file.', [
                        'code' => $class->course->code, 'class' => CourseLecturer::labelFor($class->class_number), 'found' => $found, 'expected' => $expected,
                    ])];
                }
            }
            foreach ($groupSlots as $slots) {
                if (count($slots) > 1) {
                    $warnings[] = ['row' => 0, 'message' => __('The classes of group :group do not share one slot.', ['group' => reset($slots) ?? '-'])];
                }
            }
        }

        $commit = $apply && $errors === [] && ($details !== [] || $data !== null);
        $runId = null;
        if ($commit && $details !== []) {
            $run = SchedulingRun::query()->create([
                'client_run_id' => (string) Str::uuid(),
                'batch_id' => (string) Str::uuid(),
                'faculty_id' => null,
                'study_program_id' => null,
                'source' => RunSource::IMPORT,
                'period' => $period,
                'parameter_snapshot' => [],
                'status' => RunStatus::DONE,
                'is_active' => false,
                'triggered_by' => $actor->id,
                'started_at' => now(),
                'finished_at' => now(),
            ]);
            foreach (array_chunk($details, 500) as $chunk) {
                ScheduleDetail::query()->insert(array_map(fn (array $d) => ['scheduling_run_id' => $run->id, ...$d], $chunk));
            }
            $runId = $run->id;
        }

        return ['committed' => $commit, 'run_id' => $runId, 'meetings' => count($details), 'schedule' => $schedule, 'data' => $data, 'errors' => $errors, 'warnings' => $warnings];
    }
}
