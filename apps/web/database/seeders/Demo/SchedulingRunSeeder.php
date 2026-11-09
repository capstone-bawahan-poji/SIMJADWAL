<?php

namespace Database\Seeders\Demo;

use App\Enums\Account\Role;
use App\Enums\Scheduling\RunStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\Scheduling\GaParameterPreset;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Services\Scheduling\TpbBlockService;
use App\Services\Scheduling\TpbRunResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Demo runs for the review, schedule and export pages, so they work before anyone runs the GA
 * from the desktop app. The schedules come from a seeded greedy placement, not from the GA, but
 * keep every hard constraint (lecturer, class, room, parallel limit, TPB slots).
 *
 *   - TPB: one run, active and published. Faculties are scheduled against it.
 *   - FSTI: one batch of three runs. Run 1 is active and published, with one manual override.
 *   - FPB: one run, active but not published yet (visible to its faculty admin only).
 *
 * Skipped when the faculty already has runs in the period, so re-running changes nothing.
 */
class SchedulingRunSeeder extends Seeder
{
    private const PERIOD = '2026/2027-GANJIL';

    /** @var array<int, array<int, true>> slot => lecturer_id, held by the active runs seeded so far */
    private array $activeLecturers = [];

    /** @var array<int, array<int, true>> slot => room_id, held by the active runs seeded so far */
    private array $activeRooms = [];

    public function __construct(
        private readonly TpbBlockService $tpbBlocks,
        private readonly TpbRunResolver $tpbRuns,
    ) {}

    public function run(): void
    {
        $this->seedTpb();
        $this->seedFaculty('Fakultas Sains dan Teknologi Informasi', runs: 3, publish: true);
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
        $preset = GaParameterPreset::query()->where('faculty_id', $faculty->id)->orderBy('id')->first();
        $tpbRunId = $this->tpbRuns->active(self::PERIOD)?->id;

        $classes = CourseLecturer::query()
            ->with('course')
            ->whereHas('course', fn ($q) => $q->where('is_tpb', false)
                ->whereIn('semester', [1, 3, 5, 7])
                ->whereHas('studyProgram', fn ($p) => $p->where('faculty_id', $faculty->id)))
            ->orderBy('course_id')
            ->orderBy('class_number')
            ->get();
        if ($classes->isEmpty()) {
            $this->command?->warn("No classes for {$name}, skipping demo runs.");

            return;
        }

        $batchId = (string) Str::uuid();
        foreach (range(1, $runs) as $i) {
            $seed = 12345 + $i - 1;
            $active = $i === 1;
            $details = $this->place($classes, $faculty, $seed, $active, $this->parallelLimit($preset));

            $run = SchedulingRun::query()->create([
                'client_run_id' => (string) Str::uuid(),
                'batch_id' => $batchId,
                'faculty_id' => $faculty->id,
                'tpb_run_id' => $tpbRunId,
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
                'triggered_by' => $admin->id,
                'started_at' => now()->subHour(),
                'finished_at' => now()->subHour()->addSeconds(40 + $i),
                'published_by' => $active && $publish ? $admin->id : null,
                'published_at' => $active && $publish ? now() : null,
            ]);

            foreach (array_chunk($details, 500) as $chunk) {
                ScheduleDetail::query()->insert(array_map(fn (array $d) => ['scheduling_run_id' => $run->id, ...$d], $chunk));
            }

            if ($active && $publish) {
                // One placement moved by hand, so the UI has an overridden row to show.
                $run->scheduleDetails()->orderBy('id')->first()?->update([
                    'is_overridden' => true,
                    'overridden_by' => $admin->id,
                    'overridden_at' => now(),
                ]);
            }
        }
    }

    /**
     * Greedy placement in a seeded random order. A class without a free room keeps room_id null,
     * as the GA reports it.
     *
     * @param  Collection<int, CourseLecturer>  $classes
     * @return list<array<string, int|null>>
     */
    private function place(Collection $classes, Faculty $faculty, int $seed, bool $active, int $parallelLimit): array
    {
        mt_srand($seed);
        $slots = TimeSlot::query()->orderBy('day')->orderBy('session')->pluck('id')->all();
        $rooms = Room::query()->where(fn ($q) => $q->where('faculty_id', $faculty->id)->orWhereNull('faculty_id'))
            ->orderBy('capacity')->get(['id', 'capacity']);
        $blocks = $this->tpbBlocks->blocks(self::PERIOD);
        $blockedProgram = $blocks['programs'];
        $blockedLecturer = $blocks['lecturers'];
        $blockedRoom = $blocks['rooms'];

        $takenLecturers = $this->activeLecturers;
        $takenRooms = $this->activeRooms;
        $cohorts = [];   // slot => "program:semester:class" => true
        $parallel = [];  // slot => program => count
        $details = [];

        foreach ($classes->shuffle($seed) as $class) {
            /** @var Course $course */
            $course = $class->course;
            foreach (range(1, $course->sks === 4 ? 2 : 1) as $meeting) {
                $order = $slots;
                shuffle($order);
                $placed = false;

                foreach ($order as $slot) {
                    $cohort = "{$course->study_program_id}:{$course->semester}:{$class->class_number}";
                    if (isset($cohorts[$slot][$cohort]) || isset($takenLecturers[$slot][$class->lecturer_id])
                        || ($parallel[$slot][$course->study_program_id] ?? 0) >= $parallelLimit
                        || in_array($slot, $blockedProgram[$course->study_program_id][$course->semester] ?? [], true)
                        || in_array($slot, $blockedLecturer[$class->lecturer_id] ?? [], true)) {
                        continue;
                    }

                    $room = $rooms->first(fn (Room $r) => $r->capacity >= $course->class_capacity
                        && ! isset($takenRooms[$slot][$r->id])
                        && ! in_array($slot, $blockedRoom[$r->id] ?? [], true));

                    $cohorts[$slot][$cohort] = true;
                    $takenLecturers[$slot][$class->lecturer_id] = true;
                    $parallel[$slot][$course->study_program_id] = ($parallel[$slot][$course->study_program_id] ?? 0) + 1;
                    if ($room !== null) {
                        $takenRooms[$slot][$room->id] = true;
                    }

                    $details[] = [
                        'course_id' => $course->id,
                        'class_number' => $class->class_number,
                        'meeting_number' => $meeting,
                        'lecturer_id' => $class->lecturer_id,
                        'time_slot_id' => $slot,
                        'room_id' => $room?->id,
                    ];
                    $placed = true;
                    break;
                }

                if (! $placed) {
                    $this->command?->warn("No free slot for course {$course->code} class {$class->class_number}.");
                }
            }
        }

        if ($active) {
            // Lecturers and rooms of an active schedule are taken for the next faculty too.
            $this->activeLecturers = $takenLecturers;
            $this->activeRooms = $takenRooms;
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
        $preset = GaParameterPreset::query()->whereNull('faculty_id')->orderBy('id')->first();
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

        // Lecturers and rooms of the TPB schedule are taken for every faculty.
        $this->activeLecturers = $takenLecturers;
        $this->activeRooms = $takenRooms;
        $this->tpbRuns->forget();
    }

    private function parallelLimit(?GaParameterPreset $preset): int
    {
        return $preset?->parallel_class_limit ?? 5;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(?GaParameterPreset $preset, int $seed): array
    {
        return [
            'population_size' => $preset?->population_size ?? 100,
            'parallel_class_limit' => $this->parallelLimit($preset),
            'fitness_constant' => $preset?->fitness_constant ?? 500,
            'selection_operator' => $preset?->selection_operator?->value ?? 'TS',
            'crossover_rate' => (float) ($preset?->crossover_rate ?? 0.9),
            'mutation_rate' => (float) ($preset?->mutation_rate ?? 0.5),
            'seed' => $seed,
            'weights' => ConstraintType::query()->get()->mapWithKeys(fn (ConstraintType $t) => [$t->code->value => (float) $t->weight])->all(),
        ];
    }
}
