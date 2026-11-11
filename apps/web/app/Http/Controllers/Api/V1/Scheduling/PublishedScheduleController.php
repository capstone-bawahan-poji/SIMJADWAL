<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Scheduling\ListPublishedScheduleRequest;
use App\Services\Scheduling\PublishedScheduleService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Integration API for other campus systems (SIAKAD, Gerbang ITK, the labs): the published
 * schedule only, as flat rows keyed by codes. Version 1 of this contract is fixed; a column
 * change means a new version. Clients send If-None-Match with the last ETag to skip unchanged data.
 */
class PublishedScheduleController extends ApiController
{
    public function __construct(private readonly PublishedScheduleService $published) {}

    public function index(ListPublishedScheduleRequest $request): Response
    {
        $period = $request->period();
        $filters = $request->filters();
        $etag = $this->published->etag($period, $filters);
        if (in_array($etag, $request->getETags(), true)) {
            return response()->noContent(304)->setEtag(trim($etag, '"'));
        }

        if ($request->validated('format') === 'csv') {
            return $this->csv($period, $filters)->setEtag(trim($etag, '"'));
        }

        $page = $this->published->rows($period, $filters, (int) ($request->validated('per_page') ?? 500));

        return new JsonResponse([
            'data' => $page->items(),
            'meta' => [
                'period' => $period,
                'columns' => PublishedScheduleService::COLUMNS,
                'next_cursor' => $page->nextCursor()?->encode(),
                'publications' => $this->published->publications($period),
            ],
        ], 200, ['ETag' => $etag]);
    }

    public function publications(ListPublishedScheduleRequest $request): JsonResponse
    {
        return $this->response($this->published->publications($request->period()));
    }

    /**
     * @param  array{faculty_code: string|null, study_program_code: string|null, updated_since: string|null}  $filters
     */
    private function csv(string $period, array $filters): StreamedResponse
    {
        return response()->streamDownload(function () use ($period, $filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, PublishedScheduleService::COLUMNS);
            foreach ($this->published->all($period, $filters) as $row) {
                fputcsv($out, array_map(fn ($value) => is_bool($value) ? (int) $value : $value, array_values($row)));
            }
            fclose($out);
        }, 'jadwal-'.str_replace('/', '-', $period).'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
