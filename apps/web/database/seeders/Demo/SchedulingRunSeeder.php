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
use App\Models\Scheduling\GaParameterPreset;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Services\MasterData\TpbBlockService;
use App\Services\Scheduling\Period;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Demo runs for the review, schedule and export pages, so they work before anyone runs the GA
 * from the desktop app. The schedules come from a seeded greedy placement, not from the GA, but
 * keep every hard constraint (lecturer, class, room, parallel limit, TPB slots).
 *
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

    public function __construct(private readonly TpbBlockService $tpbBlocks) {}

    public function run(): void
    {
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
        $odd = Period::isOdd(self::PERIOD);
        $blockedProgram = $this->tpbBlocks->blockedSlotsByStudyProgram($odd);
        $blockedLecturer = $this->tpbBlocks->blockedSlotsByLecturer($odd);
        $blockedRoom = $this->tpbBlocks->blockedSlotsByRoom($odd);

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
                        && ($class->room_id === null || $class->room_id === $r->id)
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