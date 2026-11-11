<?php

namespace App\Http\Controllers\Api\Internal\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Scheduling\FacultyPeriodRequest;
use App\Http\Requests\Scheduling\ImportTpbScheduleRequest;
use App\Permissions\Scheduling\SchedulingPermissions;
use App\Services\Scheduling\Import\TpbScheduleSheet;
use App\Services\Scheduling\TpbOccupancyImportService;
use App\Services\Scheduling\TpbOccupancyResolver;
use App\Services\Scheduling\TpbScheduleImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The TPB schedule entered from a file (POS 1.2):
 *   - TPB admin (tpbStore): becomes a TPB run, activated and published as usual (1.2.c),
 *   - faculty admin (facultyStore): the faculty's own record of what TPB holds (1.2.b).
 */
class TpbScheduleImportController extends ApiController
{
    public function __construct(
        protected readonly TpbScheduleImportService $tpbImport,
        protected readonly TpbOccupancyImportService $facultyImport,
        protected readonly TpbOccupancyResolver $tpb,
        protected readonly ResolvesOrgScope $orgScope,
    ) {}

    public function template(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => (new Xlsx(TpbScheduleSheet::template()))->save('php://output'),
            'template-jadwal-tpb.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function tpbStore(ImportTpbScheduleRequest $request): JsonResponse
    {
        if (! $request->user()->can(SchedulingPermissions::IMPORT_TPB) || ! $this->orgScope->canAccessScheduleScope($request->user(), null)) {
            throw new AuthorizationException;
        }
        $result = $this->tpbImport->run($request->user(), $request->validated('period'), $request->file('file')->getRealPath(), ! $request->boolean('dry_run'));

        return $this->response($result, $result['committed'] ? 201 : ($request->boolean('dry_run') || $result['errors'] === [] ? 200 : 422));
    }

    /**
     * What TPB holds for the faculty, and where it comes from (published TPB run or the faculty's import).
     */
    public function facultyShow(FacultyPeriodRequest $request): JsonResponse
    {
        $faculty = $request->faculty();
        $this->authorize('viewRoomSlots', $faculty);
        $period = $request->period();
        $occupancy = $this->tpb->forFaculty($faculty->id, $period);

        return $this->response([
            'period' => $period,
            'source' => ['type' => $occupancy['source'], 'run_id' => $occupancy['run_id'], 'import_id' => $occupancy['import_id']],
            'cells' => collect($occupancy['rooms'])
                ->flatMap(fn (array $slots, int $roomId) => array_map(fn (int $slot) => ['time_slot_id' => $slot, 'room_id' => $roomId], $slots))
                ->sortBy([['time_slot_id', 'asc'], ['room_id', 'asc']])->values()->all(),
            'import' => $this->facultyImport->show($faculty, $period),
        ]);
    }

    public function facultyStore(ImportTpbScheduleRequest $request): JsonResponse
    {
        $faculty = $request->faculty();
        $this->authorize('importTpbSchedule', $faculty);
        $result = $this->facultyImport->run(
            $request->user(),
            $faculty,
            $request->validated('period'),
            $request->file('file')->getRealPath(),
            $request->file('file')->getClientOriginalName(),
            ! $request->boolean('dry_run'),
        );

        return $this->response($result, $result['committed'] ? 201 : ($request->boolean('dry_run') || $result['errors'] === [] ? 200 : 422));
    }

    public function facultyDestroy(FacultyPeriodRequest $request): Response
    {
        $faculty = $request->faculty();
        $this->authorize('importTpbSchedule', $faculty);
        $this->facultyImport->delete($request->user(), $faculty, $request->period());

        return $this->noContent();
    }
}
