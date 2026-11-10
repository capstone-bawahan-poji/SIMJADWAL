<?php

namespace App\Services\Catalog;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only catalog for the public landing page: faculties with their programs, and active courses
 * with their parallel classes. It never exposes capacity, schedule times, rooms or lecturer contact data.
 */
readonly class CatalogService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function faculties(): Collection
    {
        return Faculty::query()
            ->with(['studyPrograms' => fn ($query) => $query->orderBy('code')])
            ->orderBy('code')
            ->get()
            ->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'code' => $faculty->code,
                'name' => $faculty->name,
                'study_programs' => $faculty->studyPrograms->map(fn ($program) => [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                ])->values()->all(),
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function courses(
        ?string $search,
        ?int $facultyId,
        ?int $studyProgramId,
        ?int $semester,
        int $perPage,
    ): LengthAwarePaginator {
        return Course::query()
            ->with(['studyProgram.faculty', 'courseLecturers' => fn ($query) => $query->with('lecturer')->orderBy('class_number')])
            ->where('is_active', true)
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")))
            ->when($facultyId, fn (Builder $query) => $query->whereRelation('studyProgram', 'faculty_id', $facultyId))
            ->when($studyProgramId, fn (Builder $query) => $query->where('study_program_id', $studyProgramId))
            ->when($semester, fn (Builder $query) => $query->where('semester', $semester))
            ->orderBy('semester')
            ->orderBy('code')
            ->paginate($perPage)
            ->through(fn (Course $course) => $this->course($course));
    }

    /**
     * @return array<string, mixed>
     */
    private function course(Course $course): array
    {
        $lecturers = $course->courseLecturers->keyBy('class_number');
        $program = $course->studyProgram;
        $faculty = $program?->faculty;

        return [
            'id' => $course->id,
            'code' => $course->code,
            'name' => $course->name,
            'sks' => $course->sks,
            'semester' => $course->semester,
            'is_tpb' => $course->is_tpb,
            'study_program' => $program ? ['id' => $program->id, 'code' => $program->code, 'name' => $program->name] : null,
            'faculty' => $faculty ? ['id' => $faculty->id, 'code' => $faculty->code, 'name' => $faculty->name] : null,
            'classes' => collect(range(1, $course->parallel_class_count))->map(function (int $number) use ($lecturers) {
                $lecturer = $lecturers->get($number)?->lecturer;

                return [
                    'class_number' => $number,
                    'class_label' => CourseLecturer::labelFor($number),
                    'lecturer' => $lecturer ? ['id' => $lecturer->id, 'name' => $lecturer->name, 'title' => $lecturer->title] : null,
                ];
            })->all(),
        ];
    }
}
