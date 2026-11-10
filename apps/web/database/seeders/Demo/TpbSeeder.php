<?php

namespace Database\Seeders\Demo;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TpbGroup;
use App\Models\MasterData\TpbOffering;
use App\Services\Scheduling\Period;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class TpbSeeder extends Seeder
{
    private const CLASS_CAPACITY = 40;

    /** Subject keyword => study program whose lecturers teach it. Others draw from every lecturer. */
    private const SUBJECT_PROGRAMS = [
        'Matematika' => 'Matematika',
        'Fisika' => 'Fisika',
        'Kimia' => 'Teknik Kimia',
        'Statistika' => 'Statistika',
        'Algoritme' => 'Informatika',
        'Bahasa Inggris' => 'Bisnis Digital',
    ];

    /** @var array<string, int> pool name => next index */
    private array $cursor = [];

    public function run(): void
    {
        foreach (glob(database_path('seeders/data/tpb/*.json')) ?: [] as $path) {
            $this->seedFile(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedFile(array $data): void
    {
        $period = $data['period'];
        if (TpbOffering::query()->where('period', $period)->exists()) {
            return;
        }

        $programIds = StudyProgram::query()->orderBy('code')->pluck('id')->all();
        $firstSemester = Period::isOdd($period) ? 1 : 2;

        foreach ($data['courses'] as $courseIndex => $row) {
            $cursor = ['regular' => $courseIndex * 3, 'mengulang' => $courseIndex * 5];
            $course = Course::query()->updateOrCreate(
                ['code' => $row['code']],
                ['study_program_id' => null, 'is_tpb' => true, 'name' => $row['name'], 'sks' => $row['sks'],
                    'semester' => null, 'parallel_class_count' => null, 'class_capacity' => null],
            );
            $classCount = collect($row['groups'])->sum(fn (array $group) => count($group['classes']));
            $offering = TpbOffering::query()->create([
                'course_id' => $course->id,
                'period' => $period,
                'parallel_class_count' => $classCount,
                'class_capacity' => self::CLASS_CAPACITY,
            ]);

            foreach ($row['groups'] as $i => $groupRow) {
                $cohort = TpbCohort::from($groupRow['cohort']);
                $group = TpbGroup::query()->create([
                    'tpb_offering_id' => $offering->id,
                    'code' => 'G'.($i + 1),
                    'cohort' => $cohort,
                ]);
                $semester = match ($cohort) {
                    TpbCohort::MABA, TpbCohort::CAMPURAN => $firstSemester,
                    TpbCohort::NON_MABA => $firstSemester + 2,
                    default => null,
                };
                $family = $cohort === TpbCohort::MENGULANG ? 'mengulang' : 'regular';
                $count = $cohort === TpbCohort::PILIHAN ? 0 : min(max(count($groupRow['programs']), 1), 6);
                $picked = [];
                for ($k = 0; $k < $count; $k++) {
                    $picked[] = $programIds[($cursor[$family]++) % count($programIds)];
                }
                $group->studyPrograms()->sync(collect($picked)->mapWithKeys(fn (int $id) => [$id => ['target_semester' => $semester]])->all());

                foreach ($groupRow['classes'] as $label) {
                    CourseLecturer::query()->create([
                        'course_id' => $course->id,
                        'class_number' => $this->classNumber($label),
                        'tpb_offering_id' => $offering->id,
                        'tpb_group_id' => $group->id,
                        'lecturer_id' => $this->nextLecturer($row['name']),
                    ]);
                }
            }
        }
    }

    private function classNumber(string $label): int
    {
        return collect(str_split($label))->reduce(fn (int $n, string $char) => $n * 26 + ord($char) - 64, 0);
    }

    private function nextLecturer(string $courseName): int
    {
        $program = collect(self::SUBJECT_PROGRAMS)->first(fn (string $p, string $keyword) => Str::contains($courseName, $keyword));
        $pool = $this->pool($program);
        $key = $program ?? '*';
        $index = $this->cursor[$key] ?? 0;
        $this->cursor[$key] = $index + 1;

        return $pool[$index % count($pool)];
    }

    /** @var array<string, list<int>> */
    private array $pools = [];

    /**
     * @return list<int>
     */
    private function pool(?string $programName): array
    {
        return $this->pools[$programName ?? '*'] ??= $this->lecturerIds($programName);
    }

    /**
     * @return list<int>
     */
    private function lecturerIds(?string $programName): array
    {
        /** @var Collection<int, int> $ids */
        $ids = Lecturer::query()
            ->when($programName, fn ($query) => $query->whereRelation('studyProgram', 'name', $programName))
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            throw new RuntimeException('No lecturers to teach TPB classes'.($programName ? " of {$programName}" : '').'. Seed master data first.');
        }

        return $ids->all();
    }
}
