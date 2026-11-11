<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\Faculty;
use App\Models\Scheduling\TpbOccupancyImport;
use App\Models\Scheduling\TpbOccupancyImportRow;
use App\Models\User;
use App\Services\Scheduling\Import\TpbScheduleSheet;
use App\Services\Shared\ChangeRecorder;
use Illuminate\Database\DatabaseManager;

/**
 * POS 1.2.b: a faculty imports the TPB schedule file it got from TPB, when TPB has no published
 * schedule in the system. Only Hari, Sesi and Ruangan are required: they tell which cells TPB holds.
 * Dosen, when found, also keeps the faculty's programs off that lecturer's TPB slots.
 * The file is the shared TPB format (docs/plans/konvensi-file-tpb.md): Grup, Peserta, Prodi and
 * Semester are read but not used yet, so the faculty gets rooms and lecturers, not program blocks.
 *
 * Rows in a room the faculty cannot use are skipped with a warning (they do not concern it).
 * A new import replaces the previous one of the faculty and period.
 */
readonly class TpbOccupancyImportService
{
    public function __construct(
        private DatabaseManager $db,
        private ChangeRecorder $changes,
        private ProgramScheduleState $state,
    ) {}

    /**
     * @return array{committed: bool, rows: int, skipped: int, errors: list<array{row: int, message: string}>, warnings: list<array{row: int, message: string}>}
     */
    public function run(User $actor, Faculty $faculty, string $period, string $path, ?string $fileName, bool $apply): array
    {
        $this->state->assertFacultyNotPublished($faculty->id, $period);

        $sheet = new TpbScheduleSheet;
        $errors = [];
        $warnings = [];
        $rows = [];
        foreach ($sheet->read($path, ['day', 'session', 'room']) as ['row' => $line, 'values' => $v]) {
            // The shared TPB file may list classes TPB has not scheduled yet: nothing to block there.
            if ($v['day'] === null && $v['session'] === null && $v['room'] === null) {
                $warnings[] = ['row' => $line, 'message' => __('The row has no Hari, Sesi, and Ruangan, it is skipped.')];

                continue;
            }
            $slotId = $sheet->timeSlotId($v['day'], $v['session']);
            if ($slotId === null) {
                $errors[] = ['row' => $line, 'message' => __('Hari and Sesi do not match a time slot.')];

                continue;
            }
            $room = $sheet->room($v['room']);
            if ($room === null || ($room->faculty_id !== null && $room->faculty_id !== $faculty->id)) {
                $warnings[] = ['row' => $line, 'message' => __('Room :room is not a room of this faculty, the row is skipped.', ['room' => $v['room'] ?? '-'])];

                continue;
            }
            $lecturerId = $sheet->lecturerId($v['lecturer']);
            if ($v['lecturer'] !== null && $lecturerId === null) {
                $warnings[] = ['row' => $line, 'message' => __('Lecturer :name not found, only the room is blocked.', ['name' => $v['lecturer']])];
            }
            $rows[] = [
                'time_slot_id' => $slotId,
                'room_id' => $room->id,
                'lecturer_id' => $lecturerId,
                'course_code' => $v['code'] !== null ? mb_substr($v['code'], 0, 20) : null,
                'course_name' => $v['name'] !== null ? mb_substr($v['name'], 0, 150) : null,
                'class_label' => $v['class'] !== null ? mb_substr($v['class'], 0, 10) : null,
                'participants' => $v['capacity'] !== null && ctype_digit($v['capacity']) ? min((int) $v['capacity'], 32767) : null,
            ];
        }

        $commit = $apply && $errors === [];
        if ($commit) {
            $this->db->transaction(function () use ($actor, $faculty, $period, $fileName, $rows) {
                TpbOccupancyImport::query()->where('faculty_id', $faculty->id)->where('period', $period)->get()->each->delete();
                $import = TpbOccupancyImport::query()->create([
                    'faculty_id' => $faculty->id,
                    'period' => $period,
                    'file_name' => $fileName !== null ? mb_substr($fileName, 0, 255) : null,
                    'imported_by' => $actor->id,
                ]);
                foreach (array_chunk($rows, 500) as $chunk) {
                    TpbOccupancyImportRow::query()->insert(array_map(fn (array $row) => ['tpb_occupancy_import_id' => $import->id, ...$row], $chunk));
                }
                $this->changes->record('tpb_occupancy_import_rows');
            });

            activity('scheduling')->causedBy($actor)->event('tpb_occupancy.imported')
                ->withProperties(['faculty_id' => $faculty->id, 'period' => $period, 'rows' => count($rows)])
                ->log(__('Imported the TPB schedule for :faculty, :period', ['faculty' => $faculty->code, 'period' => Period::label($period)]));
        }

        return ['committed' => $commit, 'rows' => count($rows), 'skipped' => count($warnings), 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(Faculty $faculty, string $period): ?array
    {
        $import = TpbOccupancyImport::query()->with(['importedBy:id,name', 'rows.room:id,code', 'rows.timeSlot:id,day,session', 'rows.lecturer:id,nip,name'])
            ->where('faculty_id', $faculty->id)->where('period', $period)->first();

        return $import === null ? null : [
            'id' => $import->id,
            'period' => $import->period,
            'file_name' => $import->file_name,
            'imported_by' => $import->importedBy ? ['id' => $import->importedBy->id, 'name' => $import->importedBy->name] : null,
            'imported_at' => $import->created_at?->toIso8601String(),
            'rows' => $import->rows->map(fn (TpbOccupancyImportRow $row) => [
                'time_slot_id' => $row->time_slot_id,
                'day' => $row->timeSlot->day->value,
                'session' => $row->timeSlot->session,
                'room' => ['id' => $row->room_id, 'code' => $row->room->code],
                'lecturer' => $row->lecturer ? ['id' => $row->lecturer->id, 'nip' => $row->lecturer->nip, 'name' => $row->lecturer->name] : null,
                'course_code' => $row->course_code,
                'course_name' => $row->course_name,
                'class_label' => $row->class_label,
                'participants' => $row->participants,
            ])->values()->all(),
        ];
    }

    public function delete(User $actor, Faculty $faculty, string $period): void
    {
        $this->state->assertFacultyNotPublished($faculty->id, $period);
        TpbOccupancyImport::query()->where('faculty_id', $faculty->id)->where('period', $period)->get()->each->delete();

        activity('scheduling')->causedBy($actor)->event('tpb_occupancy.deleted')
            ->withProperties(['faculty_id' => $faculty->id, 'period' => $period])
            ->log(__('Removed the imported TPB schedule of :faculty, :period', ['faculty' => $faculty->code, 'period' => Period::label($period)]));
    }
}
