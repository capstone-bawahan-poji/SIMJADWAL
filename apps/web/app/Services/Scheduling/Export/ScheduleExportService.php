<?php

namespace App\Services\Scheduling\Export;

use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\User;
use App\Services\Scheduling\Period;
use App\Services\Scheduling\ScheduleQueryService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Downloads the schedule the actor sees on screen with the same filters (ScheduleQueryService),
 * as xlsx, pdf or ics. The filters also name the file: lecturer, picked classes, program, faculty.
 */
readonly class ScheduleExportService
{
    /** format => exporter class */
    public const FORMATS = [
        'xlsx' => XlsxScheduleExporter::class,
        'pdf' => PdfScheduleExporter::class,
        'ics' => IcsScheduleExporter::class,
    ];

    public function __construct(
        private ScheduleQueryService $queryService,
        private Container $container,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  ListScheduleRequest::filters()
     */
    public function export(User $actor, string $period, array $filters, string $format): Response
    {
        $runs = $this->queryService->visibleRuns($actor, $period)
            ->when($filters['faculty_id'] ?? null, fn (Builder $q, int $id) => $q->where('faculty_id', $id))
            ->get(['id', 'published_at']);

        $details = $this->queryService->details($runs->modelKeys(), $filters)->get();
        if ($details->isEmpty()) {
            throw ApiException::invalidState(__('There is no schedule to export.'));
        }

        $usedRunIds = $details->pluck('scheduling_run_id')->unique();
        [$title, $subtitle, $slug] = $this->naming($period, $filters);

        $export = new ScheduleExport(
            period: $period,
            title: $title,
            subtitle: $subtitle,
            filename: $slug,
            isDraft: $runs->whereIn('id', $usedRunIds)->contains(fn ($run) => $run->published_at === null),
            details: $details,
            timeSlots: TimeSlot::query()->orderBy('day')->orderBy('session')->get(),
            generatedAt: now(IcsScheduleExporter::TIMEZONE),
        );

        /** @var ScheduleExporter $exporter */
        $exporter = $this->container->make(self::FORMATS[$format]);

        return new Response($exporter->render($export), 200, [
            'Content-Type' => $exporter->contentType(),
            'Content-Disposition' => 'attachment; filename="'.$export->filename.'.'.$exporter->extension().'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{string, string, string} title, subtitle, file name without extension
     */
    private function naming(string $period, array $filters): array
    {
        $details = [];
        $slug = [];

        if ($lecturer = $this->find(Lecturer::class, $filters['lecturer_id'] ?? null)) {
            $title = __('Teaching schedule').' · '.$lecturer->name;
            $slug[] = 'dosen-'.$lecturer->name;
        } elseif (($filters['classes'] ?? []) !== []) {
            $title = __('Personal class schedule');
            $slug[] = 'pribadi';
        } elseif ($program = $this->find(StudyProgram::class, $filters['study_program_id'] ?? null)) {
            $title = __('Class schedule').' · '.$program->name;
            $slug[] = $program->code;
        } elseif ($faculty = $this->find(Faculty::class, $filters['faculty_id'] ?? null)) {
            $title = __('Class schedule').' · '.$faculty->name;
            $slug[] = $faculty->code;
        } else {
            $title = __('Class schedule');
        }

        if ($filters['semester'] ?? null) {
            $details[] = __('Semester').' '.$filters['semester'];
            $slug[] = 'smt'.$filters['semester'];
        }
        if ($filters['class_number'] ?? null) {
            $details[] = __('Class').' '.CourseLecturer::labelFor($filters['class_number']);
            $slug[] = CourseLecturer::labelFor($filters['class_number']);
        }

        return [
            $title,
            implode(' · ', [__('Period').' '.Period::label($period), ...$details]),
            Str::slug(implode(' ', ['jadwal', ...$slug, str_replace('/', '-', $period)])),
        ];
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function find(string $model, ?int $id): mixed
    {
        return $id !== null ? $model::query()->find($id) : null;
    }
}
