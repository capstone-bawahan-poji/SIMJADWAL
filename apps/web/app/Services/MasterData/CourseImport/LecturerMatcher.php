<?php

namespace App\Services\MasterData\CourseImport;

use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use Illuminate\Support\Collection;

final class LecturerMatcher
{
    private const SUGGESTION_MIN_PERCENT = 80;
    private const SUGGESTION_LIMIT = 3;

    /** @var Collection<int, array{lecturer: Lecturer, key: string}> */
    private Collection $lecturers;

    public function __construct(private readonly StudyProgram $studyProgram)
    {
        $this->lecturers = Lecturer::query()
            ->with('studyProgram:id,code,faculty_id')
            ->orderBy('name')
            ->get(['id', 'name', 'title', 'study_program_id'])
            ->map(fn (Lecturer $lecturer) => ['lecturer' => $lecturer, 'key' => LecturerNameNormalizer::normalize($lecturer->name)])
            ->values();
    }

    /**
     * @return array{status: 'matched'|'ambiguous'|'not_found', lecturer: ?Lecturer, candidates: list<Lecturer>}
     */
    public function match(string $key): array
    {
        $hits = $this->lecturers->where('key', $key)->pluck('lecturer');

        $levels = [
            fn (Lecturer $lecturer) => $lecturer->study_program_id === $this->studyProgram->id,
            fn (Lecturer $lecturer) => $lecturer->studyProgram?->faculty_id === $this->studyProgram->faculty_id,
            fn () => true,
        ];

        foreach ($levels as $inLevel) {
            $found = $hits->filter($inLevel)->values();

            if ($found->count() === 1) {
                return ['status' => 'matched', 'lecturer' => $found->first(), 'candidates' => []];
            }

            if ($found->count() > 1) {
                return ['status' => 'ambiguous', 'lecturer' => null, 'candidates' => $found->all()];
            }
        }

        return ['status' => 'not_found', 'lecturer' => null, 'candidates' => $this->similar($key)];
    }

    public function find(int $id): ?Lecturer
    {
        return $this->lecturers->first(fn (array $entry) => $entry['lecturer']->id === $id)['lecturer'] ?? null;
    }

    public function isOtherFaculty(Lecturer $lecturer): bool
    {
        return $lecturer->studyProgram?->faculty_id !== $this->studyProgram->faculty_id;
    }

    /**
     * @return list<Lecturer>
     */
    private function similar(string $key): array
    {
        return $this->lecturers
            ->map(function (array $entry) use ($key) {
                similar_text($key, $entry['key'], $percent);

                return ['lecturer' => $entry['lecturer'], 'percent' => $percent];
            })
            ->filter(fn (array $entry) => $entry['percent'] >= self::SUGGESTION_MIN_PERCENT)
            ->sortByDesc('percent')
            ->take(self::SUGGESTION_LIMIT)
            ->pluck('lecturer')
            ->values()
            ->all();
    }
}