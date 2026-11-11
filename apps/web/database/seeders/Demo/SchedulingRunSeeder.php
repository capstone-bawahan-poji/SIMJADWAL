<?php

namespace Database\Seeders\Demo;

use App\Enums\Account\Role;
use App\Enums\Scheduling\RunStatus;
use App\Enums\Scheduling\SubmissionStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\Scheduling\GaParameterPreset;
use App\Models\Scheduling\ProgramScheduleSubmission;
use App\Models\Scheduling\RoomSlotAllocation;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulePublication;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Services\Scheduling\PublishedScheduleService;
use App\Services\Scheduling\TpbBlockService;
use App\Services\Scheduling\TpbRunResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Demo data for the scheduling pages, so they work before anyone runs the GA from the desktop
 * app. Schedules come from a seeded greedy placement, not from the GA, but keep every hard
 * constraint (lecturer, class, cell, TPB slots).
 *
 *   - TPB: one run, active and published. Faculties hand out rooms around it.
 *   - FSTI: every program gets cells and two runs; run 1 is active, accepted and published with
 *     the faculty (one manual override).
 *   - FPB: every program gets cells and one active run, handed to the faculty but not reviewed.
 *
 * Skipped when the faculty already has runs in the period, so re-running changes nothing.
 */
class SchedulingRunSeeder extends Seeder
{
    private const PERIOD = '2026/2027-GANJIL';

    /** Cells a program gets on top of what its meetings need. */
    private const SPARE_CELLS = 0.25;

    /** @var array<int, array<int, true>> slot => lecturer_id, held by the active runs seeded so far */
    private array $activeLecturers = [];

    public function __construct(
        private readonly TpbBlockService $tpbBlocks,
        private readonly TpbRunResolver $tpbRuns,
        private readonly PublishedScheduleService $published,
    ) {}

    public function run(): void
    {
        $this->seedTpb();
        $this->seedFaculty('Fakultas Sains dan Teknologi Informasi', runs: 2, publish: true);
        $this->seedFaculty('Fakultas Pembangunan Berkelanjutan', runs: 1, publish: false);
    }

    private function seedFaculty(string $name, int $runs, bool $publish): void
    {
        $faculty = Faculty::query()->where('name', $name)->first();
        if ($faculty === null || SchedulingRun::query()->where('faculty_id', $faculty->id)->where('period', self::PERIOD)->exists()) {
            return;
        }
        $admin = User::query()->role(Role::FACULTY_ADMIN->value)->where('faculty_id', $faculty->id)->first()
            ?? User::query()->role(Role::SUPER_ADMIN->value)->firstOrFail();

        $classesByProgram = CourseLecturer::query()
            ->with('course')
            ->whereHas('course', fn ($q) => $q->where('is_tpb', false)->where('is_active', true)
                ->whereIn('semester', [1, 3, 5, 7])
                ->whereHas('studyProgram', fn ($p) => $p->where('faculty_id', $faculty->id)))
            ->orderBy('course_id')
            ->orderBy('class_number')
            ->get()
            ->groupBy(fn (CourseLecturer $class) => $class->course->study_program_id);
        if ($classesByProgram->isEmpty()) {
            $this->command?->warn("No classes for {$name}, skipping demo runs.");

            return;
        }

        $cells = $this->allocate($faculty, $classesByProgram, $admin);
        $overridden = false;

        foreach ($classesByProgram as $programId => $classes) {
            $programAdmin = User::query()->role(Role::STUDY_PROGRAM_ADMIN->value)->where('study_program_id', $programId)->first() ?? $admin;
            $preset = GaParameterPreset::query()->where('study_program_id', $programId)->orderBy('id')->first();
            $batchId = (string) Str::uuid();

            foreach (range(1, $runs) as $i) {
                $seed = 12345 + $i - 1;
                $active = $i === 1;
                $details = $this->place($classes, $cells[$programId] ?? [], $seed, $active);
                $run = SchedulingRun::query()->create([
                    'client_run_id' => (string) Str::uuid(),
                    'batch_id' => $batchId,
                    'faculty_id' => $faculty->id,
                    'study_program_id' => $programId,
                    'tpb_run_id' => $this->tpbRuns->active(self::PERIOD)?->id,
                    'period' => self::PERIOD,
                    'preset_id' => $preset?->id,
                    'parameter_snapshot' => $this->snapshot($preset, $seed),
                    'status' => RunStatus::DONE,
                    'current_generation' => 600 + $i * 37,
                    'best_fitness' => 500 - $i * 3,
                    'current_fitness' => 500 - $i * 3,
                    'max_fitness' => 500,
                    'best_generation' => 400 + $i * 37,
                    'fitness_history' => [420, 470, 490, 500 - $i * 3],
                    'is_active' => $active,
                    'triggered_by' => $programAdmin->id,
                    'started_at' => now()->subHour(),
                    'finished_at' => now()->subHour()->addSeconds(40 + $i),
                    'published_by' => $active && $publish ? $admin->id : null,
                    'published_at' => $active && $publish ? now() : null,
                ]);

                foreach (array_chunk($details, 500) as $chunk) {
                    ScheduleDetail::query()->insert(array_map(fn (array $d) => ['scheduling_run_id' => $run->id, ...$d], $chunk));
                }

                if (! $active) {
                    continue;
                }
                ProgramScheduleSubmission::query()->create([
                    'period' => self::PERIOD,
                    'study_program_id' => $programId,
                    'scheduling_run_id' => $run->id,
                    'status' => $publish ? SubmissionStatus::PUBLISHED : SubmissionStatus::SUBMITTED,
                    'submitted_by' => $programAdmin->id,
                    'submitted_at' => now()->subMinutes(30),
                    'reviewed_by' => $publish ? $admin->id : null,
                    'reviewed_at' => $publish ? now()->subMinutes(10) : null,
                ]);
                if ($publish && ! $overridden) {
                    $overridden = true;
                    // One placement moved by hand, so the UI has an overridden row to show.
                    $run->scheduleDetails()->orderBy('id')->first()?->update([
                        'is_overridden' => true,
                        'overridden_by' => $programAdmin->id,
                        'overridden_at' => now(),
                    ]);
                }
            }
        }

        if ($publish) {
            SchedulePublication::query()->create([
                'faculty_id' => $faculty->id,
                'period' => self::PERIOD,
                'published_by' => $admin->id,
                'published_at' => now(),
                'checksum' => $this->published->checksum($faculty->id, self::PERIOD),
            ]);
        }
    }

    /**
     * Hands every program the cells its meetings need plus a spare quarter, taken in a seeded
     * order from the faculty's and the shared rooms that TPB and other faculties leave free.
     *
     * @param  Collection<int, Collection<int, CourseLecturer>>  $classesByProgram
     * @return array<int, list<array{time_slot_id: int, room_id: int, capacity: int}>> program => cells
     */
    private function allocate(Faculty $faculty, Collection $classesByProgram, User $admin): array
    {
        mt_srand(777);
        $tpbRooms = $this->tpbBlocks->blocks(self::PERIOD)['rooms'];
        $taken = RoomSlotAllocation::query()->where('period', self::PERIOD)->get(['time_slot_id', 'room_id'])
            ->mapWithKeys(fn (RoomSlotAllocation $a) => ["{$a->time_slot_id}:{$a->room_id}" => true])->all();
        $free = [];
        foreach (Room::query()->where(fn ($q) => $q->where('faculty_id', $faculty->id)->orWhereNull('faculty_id'))->get(['id', 'capacity']) as $room) {
            foreach (TimeSlot::query()->pluck('id') as $slot) {
                if (! isset($taken["{$slot}:{$room->id}"]) && ! in_array($slot, $tpbRooms[$room->id] ?? [], true)) {
                    $free[] = ['time_slot_id' => $slot, 'room_id' => $room->id, 'capacity' => $room->capacity];
                }
            }
        }
        shuffle($free);

        $cells = [];
        $rows = [];
        foreach ($classesByProgram as $programId => $classes) {
            $meetings = $classes->sum(fn (CourseLecturer $c) => $c->course->sks === 4 ? 2 : 1);
            $want = (int) ceil($meetings * (1 + self::SPARE_CELLS));
            $biggest = (int) $classes->max(fn (CourseLecturer $c) => $c->course->class_capacity);
            // Rooms that fit the biggest class first, so every class finds a room.
            usort($free, fn (array $a, array $b) => ($b['capacity'] >= $biggest) <=> ($a['capacity'] >= $biggest));
            foreach (array_splice($free, 0, min($want, count($free))) as $cell) {
                $cells[$programId][] = $cell;
                $rows[] = [
                    'period' => self::PERIOD,
                    'faculty_id' => $faculty->id,
                    'study_program_id' => $programId,
                    'time_slot_id' => $cell['time_slot_id'],
                    'room_id' => $cell['room_id'],
                    'allocated_by' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            RoomSlotAllocation::query()->insert($chunk);
        }

        return $cells;
    }

    /**
     * Greedy placement in a seeded random order into the program's cells, smallest fitting room
     * first. A meeting with no free cell is left out with a warning.
     *
     * @param  Collection<int, CourseLecturer>  $classes
     * @param  list<array{time_slot_id: int, room_id: int, capacity: int}>  $cells
     * @return list<array<string, int|null>>
     */
    private function place(Collection $classes, array $cells, int $seed, bool $active): array
    {
        mt_srand($seed);
        $blocks = $this->tpbBlocks->blocks(self::PERIOD);
        usort($cells, fn (array $a, array $b) => $a['capacity'] <=> $b['capacity']);
        shuffle($cells);
        usort($cells, fn (array $a, array $b) => $a['capacity'] <=> $b['capacity']);

        $takenLecturers = $this->activeLecturers;
        $usedCells = [];
        $cohorts = [];
        $details = [];

        foreach ($classes->shuffle($seed) as $class) {
            /** @var Course $course */
            $course = $class->course;
            foreach (range(1, $course->sks === 4 ? 2 : 1) as $meeting) {
                $cohort = "{$course->semester}:{$class->class_number}";
                $cell = collect($cells)->first(fn (array $c) => ! isset($usedCells["{$c['time_slot_id']}:{$c['room_id']}"])
                    && $c['capacity'] >= $course->class_capacity
                    && ! isset($cohorts[$c['time_slot_id']][$cohort])
                    && ! isset($takenLecturers[$c['time_slot_id']][$class->lecturer_id])
                    && ! in_array($c['time_slot_id'], $blocks['programs'][$course->study_program_id][$course->semester] ?? [], true)
                    && ! in_array($c['time_slot_id'], $blocks['lecturers'][$class->lecturer_id] ?? [], true));
                if ($cell === null) {
                    $this->command?->warn("No free cell for course {$course->code} class {$class->class_number}.");

                    continue;
                }

                $usedCells["{$cell['time_slot_id']}:{$cell['room_id']}"] = true;
                $cohorts[$cell['time_slot_id']][$cohort] = true;
                $takenLecturers[$cell['time_slot_id']][$class->lecturer_id] = true;
                $details[] = [
                    'course_id' => $course->id,
                    'class_number' => $class->class_number,
                    'meeting_number' => $meeting,
                    'lecturer_id' => $class->lecturer_id,
                    'time_slot_id' => $cell['time_slot_id'],
                    'room_id' => $cell['room_id'],
                ];
            }
        }

        if ($active) {
            // Lecturers of an active schedule are taken for the next program too.
            $this->activeLecturers = $takenLecturers;
        }

        return $details;
    }

    /**
     * The TPB run: every group meeting in one slot shared by its classes, avoiding lecturer clashes
     * and programs that would attend two regular groups of the same semester at once.
     */
    private function seedTpb(): void
    {
        if (SchedulingRun::query()->whereNull('faculty_id')->where('period', self::PERIOD)->exists()) {
            return;
        }
        $groups = TpbGroup::query()
            ->with(['offering.course', 'studyPrograms:id', 'courseLecturers'])
            ->whereRelation('offering', 'period', self::PERIOD)
            ->orderBy('id')
            ->get();
        if ($groups->isEmpty()) {
            return;
        }

        $admin = User::query()->role(Role::TPB_ADMIN->value)->first() ?? User::query()->role(Role::SUPER_ADMIN->value)->firstOrFail();
        $preset = GaParameterPreset::query()->whereNull('faculty_id')->whereNull('study_program_id')->orderBy('id')->first();
        $seed = 12345;
        mt_srand($seed);
        $slots = TimeSlot::query()->orderBy('day')->orderBy('session')->pluck('id')->all();
        $rooms = Room::query()->whereNull('faculty_id')->orderBy('capacity')->get(['id', 'capacity']);

        $takenLecturers = $takenRooms = $cohorts = [];
        $details = [];
        foreach ($groups->shuffle($seed) as $group) {
            $offering = $group->offering;
            $regular = $group->cohort->isRegular();
            foreach (range(1, $offering->course->sks === 4 ? 2 : 1) as $meeting) {
                $order = $slots;
                shuffle($order);
                $slot = collect($order)->first(fn (int $slot) => $group->courseLecturers->every(fn (CourseLecturer $c) => ! isset($takenLecturers[$slot][$c->lecturer_id]))
                    && (! $regular || $group->studyPrograms->every(fn ($p) => ! isset($cohorts[$slot]["{$p->id}:{$p->pivot->target_semester}"]))));
                if ($slot === null) {
                    $this->command?->warn("No free slot for TPB group {$offering->course->code} {$group->code}.");

                    continue;
                }

                if ($regular) {
                    $group->studyPrograms->each(function ($p) use (&$cohorts, $slot) {
                        $cohorts[$slot]["{$p->id}:{$p->pivot->target_semester}"] = true;
                    });
                }
                foreach ($group->courseLecturers as $class) {
                    $room = $rooms->first(fn (Room $r) => $r->capacity >= $offering->class_capacity && ! isset($takenRooms[$slot][$r->id]));
                    $takenLecturers[$slot][$class->lecturer_id] = true;
                    if ($room !== null) {
                        $takenRooms[$slot][$room->id] = true;
                    }
                    $details[] = [
                        'course_id' => $offering->course_id,
                        'class_number' => $class->class_number,
                        'meeting_number' => $meeting,
                        'lecturer_id' => $class->lecturer_id,
                        'time_slot_id' => $slot,
                        'room_id' => $room?->id,
                    ];
                }
            }
        }

        $run = SchedulingRun::query()->create([
            'client_run_id' => (string) Str::uuid(),
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => null,
            'period' => self::PERIOD,
            'preset_id' => $preset?->id,
            'parameter_snapshot' => $this->snapshot($preset, $seed),
            'status' => RunStatus::DONE,
            'current_generation' => 640,
            'best_fitness' => 497,
            'current_fitness' => 497,
            'max_fitness' => 500,
            'best_generation' => 431,
            'fitness_history' => [420, 470, 490, 497],
            'is_active' => true,
            'triggered_by' => $admin->id,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHours(2)->addSeconds(50),
            'published_by' => $admin->id,
            'published_at' => now()->subHour(),
        ]);
        foreach (array_chunk($details, 500) as $chunk) {
            ScheduleDetail::query()->insert(array_map(fn (array $d) => ['scheduling_run_id' => $run->id, ...$d], $chunk));
        }

        // Lecturers of the TPB schedule are taken for every program.
        $this->activeLecturers = $takenLecturers;
        $this->tpbRuns->forget();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(?GaParameterPreset $preset, int $seed): array
    {
        return [
            'population_size' => $preset?->population_size ?? 100,
            'parallel_class_limit' => $preset?->parallel_class_limit ?? 5,
            'fitness_constant' => $preset?->fitness_constant ?? 500,
            'selection_operator' => $preset?->selection_operator?->value ?? 'TS',
            'crossover_rate' => (float) ($preset?->crossover_rate ?? 0.9),
            'mutation_rate' => (float) ($preset?->mutation_rate ?? 0.5),
            'seed' => $seed,
            'weights' => ConstraintType::query()->get()->mapWithKeys(fn (ConstraintType $t) => [$t->code->value => (float) $t->weight])->all(),
        ];
    }
}
