<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\MasterData\TpbOffering\ImportTpbOfferingRequest;
use App\Models\MasterData\TpbOffering;
use App\Rules\Scheduling\PeriodRule;
use App\Services\MasterData\TpbImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TpbImportController extends ApiController
{
    public function __construct(private readonly TpbImportService $importService) {}

    public function template(Request $request): StreamedResponse
    {
        $this->authorize('create', TpbOffering::class);
        $query = $request->validate(['period' => ['nullable', new PeriodRule], 'prefill' => ['nullable', 'boolean']]);
        $period = $query['period'] ?? config('scheduling.current_period');
        $prefill = $request->boolean('prefill');

        return response()->streamDownload(
            fn () => (new Xlsx($this->importService->template($period, $prefill)))->save('php://output'),
            $prefill ? 'data-tpb-'.Str::slug($period).'.xlsx' : 'template-tpb.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * dry_run = preview (nothing is saved). Otherwise saved only when the file has no errors (422 else).
     */
    public function store(ImportTpbOfferingRequest $request): JsonResponse
    {
        $result = $this->importService->run($request->file('file')->getRealPath(), $request->validated('period'), ! $request->boolean('dry_run'));

        return $this->response($result, $result['committed'] ? 201 : ($request->boolean('dry_run') || $result['errors'] === [] ? 200 : 422));
    }
}
