<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\BaseDataCollectable;

abstract class ApiController extends Controller
{
    use AuthorizesRequests;

    protected function response(mixed $data, int $status = 200): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            return new JsonResponse([
                'data' => collect($data->items())->map(fn ($item) => $this->transform($item))->all(),
                'meta' => [
                    'pagination' => [
                        'page' => $data->currentPage(),
                        'per_page' => $data->perPage(),
                        'total' => $data->total(),
                        'last_page' => $data->lastPage(),
                    ],
                ],
            ], $status);
        }

        return new JsonResponse(['data' => $this->transform($data)], $status);
    }

    protected function noContent(int $status = Response::HTTP_NO_CONTENT): Response
    {
        return response()->noContent($status);
    }

    private function transform(mixed $item): mixed
    {
        return $item instanceof BaseData || $item instanceof BaseDataCollectable ? $item->toArray() : $item;
    }
}
