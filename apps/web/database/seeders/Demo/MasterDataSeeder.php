<?php

namespace Database\Seeders\Demo;

use App\Enums\Constraint\ConstraintCode;
use App\Enums\Constraint\ConstraintStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Loads anonymized demo master data from database/seeders/data/master/{Str::slug(study program name)}.json:
 *
 *   prefix       two-letter program code, also used for admin account emails
 *   lecturers    [{code, name}]
 *   courses      [{code, name, sks, semester, classes: [{class_number, lecturer_code}]}]
 *   preferences  [{lecturer_code, constraint_code, day, session}]
 *
 * Two passes, because a lecturer may teach in another program: all lecturers and courses first,
 * then teaching assignments and preferences with a global lecturer_code lookup.
 * Every seeded program is marked as submitted.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $programs = self::programs();

        foreach ($programs as ['program' => $studyProgram, 'data' => $data]) {
            $this->seedLecturers($data['lecturers'], $studyProgram);
            $this->seedCourses($data['courses'], $studyProgram);
        }

        $lecturerIds = Lecturer::query()->pluck('id', 'code');
        $courseIds = Course::query()->pluck('id', 'code');
        $timeSlotIds = TimeSlot::query()->get()->mapWithKeys(fn (TimeSlot $slot) => ["{$slot->day->value}-{$slot->session}" => $slot->id]);
        $constraintTypeIds = ConstraintType::query()->pluck('id', 'code');

        foreach ($programs as ['program' => $studyProgram, 'data' => $data]) {
            $this->seedCourseLecturers($data['courses'], $courseIds, $lecturerIds);
            $this->seedPreferences($data['preferences'], $lecturerIds, $timeSlotIds, $constraintTypeIds);

            $studyProgram->update([
                'constraint_status' => ConstraintStatus::SUBMITTED,
                'constraint_submitted_at' => $studyProgram->constraint_submitted_at ?? now(),
            ]);
        }
    }

    /**
     * @return Collection<string, array{program: StudyProgram, data: array<string, mixed>}> slug => program and file contents
     */
    public static function programs(): Collection
    {
        $programs = StudyProgram::query()->get()->keyBy(fn (StudyProgram $program) => Str::slug($program->name));

        return collect(glob(database_path('seeders/data/master/*.json')) ?: [])
            ->mapWithKeys(function (string $path) use ($programs) {
                $slug = basename($path, '.json');

                if (! $programs->has($slug)) {
                    throw new RuntimeException("Demo data file [{$slug}.json] does not match any study program slug.");
                }

                return [$slug => [
                    'program' => $programs->get($slug),
                    'data' => json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR),
                ]];
            });
    }

    /**
     * @param  list<array{code: string, name: string}>  $lecturers
     */
    private function seedLecturers(array $lecturers, StudyProgram $studyProgram): void
    {
        foreach ($lecturers as $lecturer) {
            Lecturer::query()->updateOrCreate(
                ['code' => $lecturer['code']],
                ['study_program_id' => $studyProgram->id, 'name' => $lecturer['name'], 'title' => null],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     */
    private function seedCourses(array $courses, StudyProgram $studyProgram): void
    {
        foreach ($courses as $course) {
            Course::query()->updateOrCreate(
                ['code' => $course['code']],
                [
                    'study_program_id' => $studyProgram->id,
                    'name' => $course['name'],
                    'sks' => $course['sks'],
                    'semester' => $course['semester'],
                    'parallel_class_count' => count($course['classes']),
                    'class_capacity' => config('seeding.class_capacity'),
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     * @param  Collection<string, int>  $courseIds
     * @param  Collection<string, int>  $lecturerIds
     */
    private function seedCourseLecturers(array $courses, Collection $courseIds, Collection $lecturerIds): void
    {
        foreach ($courses as $course) {
            foreach ($course['classes'] as $class) {
                CourseLecturer::query()->updateOrCreate(
                    ['course_id' => $this->id($courseIds, $course['code'], 'Course'), 'class_number' => $class['class_number']],
                    ['lecturer_id' => $this->id($lecturerIds, $class['lecturer_code'], 'Lecturer')],
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $preferences
     * @param  Collection<string, int>  $lecturerIds
     * @param  Collection<string, int>  $timeSlotIds
     * @param  Collection<string, int>  $constraintTypeIds
     */
    private function seedPreferences(array $preferences, Collection $lecturerIds, Collection $timeSlotIds, Collection $constraintTypeIds): void
    {
        foreach ($preferences as $preference) {
            $code = ConstraintCode::from($preference['constraint_code']);

            if (! in_array($code, ConstraintCode::preferenceCodes(), true)) {
                throw new RuntimeException("Preference code [{$code->value}] must be SC_INGIN or SC_HINDARI.");
            }

            LecturerPreference::query()->updateOrCreate(
                [
                    'lecturer_id' => $this->id($lecturerIds, $preference['lecturer_code'], 'Lecturer'),
                    'time_slot_id' => $this->id($timeSlotIds, "{$preference['day']}-{$preference['session']}", 'Time slot'),
                ],
                ['constraint_type_id' => $constraintTypeIds[$code->value]],
            );
        }
    }

    /**
     * @param  Collection<string, int>  $ids
     */
    private function id(Collection $ids, string $key, string $label): int
    {
        return $ids->get($key) ?? throw new RuntimeException("{$label} [{$key}] not found.");
    }
}
