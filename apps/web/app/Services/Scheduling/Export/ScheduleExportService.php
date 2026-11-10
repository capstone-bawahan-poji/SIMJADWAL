<?php

namespace App\Services\Scheduling\Export;

use App\Enums\Account\Role;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Services\Scheduling\Period;
use App\Services\Scheduling\ScheduleQueryService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

/**
 * Downloads the schedule the actor sees on screen with the same filters (ScheduleQueryService),
 * as xlsx, pdf or ics. The filters also name the file: lecturer, picked classes, program, faculty.
 */
readonly class ScheduleExportService
{
    public const FORMATS = [
        'xlsx' => XlsxScheduleExporter::class,
        'pdf' => PdfScheduleExporter::class,
        'ics' => IcsScheduleExporter::class,
    ];

    public const ROOM_GRID_FORMATS = [
        'xlsx' => RoomGridXlsxExporter::class,
        'pdf' => RoomGridPdfExporter::class,
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
            ->when(($filters['scope'] ?? null) === 'tpb', fn (Builder $q) => $q->whereNull('faculty_id'))
            ->when(($filters['scope'] ?? null) === 'faculty', fn (Builder $q) => $q->whereNotNull('faculty_id'))
            ->get(['id', 'published_at']);

        $file = $this->render($runs, $period, $filters, $format, true);

        return new Response($file['content'], 200, [
            'Content-Type' => $file['content_type'],
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function roomGrid(User $actor, string $period, ?string $scope, ?int $facultyId): ?RoomGridExport
    {
        [$scope, $facultyId, $mode] = match (true) {
            $actor->hasRole(Role::SUPER_ADMIN->value) => [$scope, $facultyId, ExportColors::MODE_SUPERADMIN],
            $actor->hasRole(Role::TPB_ADMIN->value) => ['tpb', null, ExportColors::MODE_TPB],
            $actor->hasRole(Role::FACULTY_ADMIN->value) => ['faculty', $actor->faculty_id, ExportColors::MODE_FACULTY],
            default => throw ApiException::invalidState(__('Your role cannot export the room grid.')),
        };

        $runs = $this->queryService->publishedRuns($period)
            ->when($facultyId, fn (Builder $q, int $id) => $q->where('faculty_id', $id))
            ->when($scope === 'tpb', fn (Builder $q) => $q->whereNull('faculty_id'))
            ->when($scope === 'faculty', fn (Builder $q) => $q->whereNotNull('faculty_id'))
            ->get(['id']);

        $details = $this->queryService->details($runs->modelKeys(), [])->get();
        if ($details->isEmpty()) {
            return null;
        }

        // TPB only uses the shared rooms; a faculty also its own. A superadmin sees every room.
        $rooms = Room::query()
            ->when($scope === 'tpb', fn (Builder $q) => $q->whereNull('faculty_id'))
            ->when($scope === 'faculty' && $facultyId, fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('faculty_id')->orWhere('faculty_id', $facultyId)))
            ->get();
        $missing = $details->pluck('room_id')->filter()->unique()->diff($rooms->modelKeys());
        if ($missing->isNotEmpty()) {
            $rooms = $rooms->concat(Room::query()->withTrashed()->whereIn('id', $missing)->get());
        }

        $faculty = $facultyId ? Faculty::query()->find($facultyId) : null;
        $label = match (true) {
            $scope === 'tpb' => 'TPB',
            $faculty !== null => __('Faculty').' '.$faculty->code,
            $scope === 'faculty' => __('Faculty'),
            default => __('TPB and faculty'),
        };

        $facultyCodes = StudyProgram::query()->join('faculties', 'faculties.id', '=', 'study_programs.faculty_id')
            ->pluck('faculties.code', 'study_programs.id')->all();

        return new RoomGridExport(
            title: __('All schedules').' '.$label,
            subtitle: __('Period').' '.Period::label($period).' · '.__('Published schedule'),
            filename: Str::slug('seluruh jadwal '.($scope === 'tpb' ? 'tpb' : ($faculty?->code ?? ($scope === 'faculty' ? 'fakultas' : 'tpb fakultas'))).' '.str_replace('/', '-', $period)),
            grid: new RoomGrid($details, TimeSlot::query()->orderBy('day')->orderBy('session')->get(), $rooms, $mode, $facultyCodes),
            generatedAt: now(IcsScheduleExporter::TIMEZONE),
        );
    }

    public function exportRoomGrid(User $actor, string $period, ?string $scope, ?int $facultyId, string $format): Response
    {
        $export = $this->roomGrid($actor, $period, $scope, $facultyId)
            ?? throw ApiException::invalidState(__('There is no published schedule to export.'));

        /** @var RoomGridExporter $exporter */
        $exporter = $this->container->make(self::ROOM_GRID_FORMATS[$format]);

        return new Response($exporter->render($export), 200, [
            'Content-Type' => $exporter->contentType(),
            'Content-Disposition' => 'attachment; filename="'.$export->filename.'.'.$exporter->extension().'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Same file for a caller without an account: published runs only, no override markers.
     *
     * @param  array<string, mixed>  $filters
     * @return array{content: string, content_type: string, filename: string}
     */
    public function exportPublished(string $period, array $filters, string $format): array
    {
        $runs = $this->queryService->publishedRuns($period)->get(['id', 'published_at']);

        return $this->render($runs, $period, $filters, $format, false);
    }

    /**
     * The published schedule lines of the picked classes, for the body of the email.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ScheduleDetail>
     */
    public function publishedDetails(string $period, array $filters): Collection
    {
        $runIds = $this->queryService->publishedRuns($period)->pluck('id')->all();

        return $this->queryService->details($runIds, $filters)->get();
    }

    /**
     * @param  Collection<int, SchedulingRun>  $runs
     * @param  array<string, mixed>  $filters
     * @return array{content: string, content_type: string, filename: string}
     */
    private function render(Collection $runs, string $period, array $filters, string $format, bool $showOverrideIndicators): array
    {
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
            showOverrideIndicators: $showOverrideIndicators,
            details: $details,
            timeSlots: TimeSlot::query()->orderBy('day')->orderBy('session')->get(),
            generatedAt: now(IcsScheduleExporter::TIMEZONE),
            cohorts: $this->cohorts($details, $period),
        );

        /** @var ScheduleExporter $exporter */
        $exporter = $this->container->make(self::FORMATS[$format]);

        return [
            'content' => $exporter->render($export),
            'content_type' => $exporter->contentType(),
            'filename' => $export->filename.'.'.$exporter->extension(),
        ];
    }

    /**
     * Cohort of every TPB class in the export, for the colours of the PDF matrix.
     *
     * @param  SupportCollection<int, ScheduleDetail>  $details
     * @return array<string, string>
     */
    private function cohorts(SupportCollection $details, string $period): array
    {
        $courseIds = $details->filter(fn (ScheduleDetail $d) => $d->course->is_tpb)->pluck('course_id')->unique();
        if ($courseIds->isEmpty()) {
            return [];
        }

        return CourseLecturer::query()
            ->with('tpbGroup:id,cohort')
            ->whereIn('course_id', $courseIds)
            ->whereRelation('tpbOffering', 'period', $period)
            ->get()
            ->filter(fn (CourseLecturer $class) => $class->tpbGroup !== null)
            ->mapWithKeys(fn (CourseLecturer $class) => ["{$class->course_id}:{$class->class_number}" => $class->tpbGroup->cohort->value])
            ->all();
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
        } elseif (($filters['scope'] ?? null) === 'tpb') {
            $title = __('TPB schedule');
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
