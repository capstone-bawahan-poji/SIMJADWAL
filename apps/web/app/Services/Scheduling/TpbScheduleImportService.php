<?php

namespace App\Services\Scheduling;

use App\Enums\Scheduling\RunSource;
use App\Enums\Scheduling\RunStatus;
use App\Models\MasterData\CourseLecturer;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Services\Scheduling\Import\TpbScheduleSheet;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * POS 1.2.c: the TPB admin enters the TPB schedule from a file instead of running the GA. The TPB
 * offerings, groups and lecturers of the period must already be in the system (Mapping, or the
 * TPB data import), so every row is one meeting of a known class: Kode MK + Kelas + Hari + Sesi + Ruang.
 *
 * The result is an ordinary TPB run (source import), not active. The TPB admin activates and
 * publishes it like a GA run, and faculties then get its rooms, lecturers and program blocks.
 */
readonly class TpbScheduleImportService
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * @return array{committed: bool, run_id: int|null, meetings: int, errors: list<array{row: int, message: string}>, warnings: list<array{row: int, message: string}>}
     */
    public function run(User $actor, string $period, string $path, bool $apply): array
    {
        $sheet = new TpbScheduleSheet;
        $classes = CourseLecturer::query()
            ->with(['course:id,code,name,sks', 'tpbGroup:id,code'])
            ->whereRelation('tpbOffering', 'period', $period)
            ->get()
            ->keyBy(fn (CourseLecturer $c) => mb_strtoupper($c->course->code).':'.$c->class_number);

        $errors = [];
        $warnings = [];
        $details = [];
        $meetings = [];
        $lecturerAt = [];
        $roomAt = [];
        $groupSlots = [];
        foreach ($sheet->read($path, ['code', 'class', 'day', 'session', 'room']) as ['row' => $line, 'values' => $v]) {
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
            $meeting = ($meetings[$key] ?? 0) + 1;
            if ($meeting > ($class->course->sks === 4 ? 2 : 1)) {
                $errors[] = ['row' => $line, 'message' => __('Class :code :class has more meetings than its SKS allow.', ['code' => $v['code'], 'class' => $v['class']])];

                continue;
            }
            $meetings[$key] = $meeting;
            $roomAt["{$slotId}:{$room->id}"] = $line;
            $lecturerAt["{$slotId}:{$class->lecturer_id}"] = $line;
            $groupSlots["{$class->tpb_group_id}:{$meeting}"][$slotId] = $class->tpbGroup?->code;
            if ($room->capacity < (int) ($v['participants'] ?? 0)) {
                $warnings[] = ['row' => $line, 'message' => __('Room :room is smaller than the class.', ['room' => $room->code])];
            }
            $details[] = [
                'course_id' => $class->course_id,
                'class_number' => $class->class_number,
                'meeting_number' => $meeting,
                'lecturer_id' => $class->lecturer_id,
                'time_slot_id' => $slotId,
                'room_id' => $room->id,
            ];
        }

        foreach ($classes as $key => $class) {
            $expected = $class->course->sks === 4 ? 2 : 1;
            $found = $meetings["{$class->course_id}:{$class->class_number}"] ?? 0;
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

        $commit = $apply && $errors === [] && $details !== [];
        $runId = null;
        if ($commit) {
            $runId = $this->db->transaction(function () use ($actor, $period, $details) {
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

                return $run->id;
            });

            activity('scheduling')->causedBy($actor)->event('scheduling_run.imported')
                ->withProperties(['scheduling_run_id' => $runId, 'meetings' => count($details)])
                ->log(__('Imported the TPB schedule of :period as run :id', ['period' => Period::label($period), 'id' => $runId]));
        }

        return ['committed' => $commit, 'run_id' => $runId, 'meetings' => count($details), 'errors' => $errors, 'warnings' => $warnings];
    }
}
