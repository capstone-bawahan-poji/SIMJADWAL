<?php

namespace App\Http\Controllers\Api\Internal\Constraint;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Constraint\ReplaceLecturerPreferencesRequest;
use App\Models\MasterData\Lecturer;
use App\Services\Constraint\LecturerPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LecturerPreferenceController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewPreferences,lecturer', only: ['show']),
            new Middleware('can:updatePreferences,lecturer', only: ['update']),
        ];
    }

    public function __construct(private readonly LecturerPreferenceService $preferenceService) {}

    public function show(Lecturer $lecturer): JsonResponse
    {
        return $this->preferences($lecturer, $this->preferenceService->getPreferences($lecturer));
    }

    public function update(ReplaceLecturerPreferencesRequest $request, Lecturer $lecturer): JsonResponse
    {
        return $this->preferences($lecturer, $this->preferenceService->replacePreferences($lecturer, $request->validated('preferences')));
    }

    /**
     * @param  list<array{time_slot_id: int, type: string}>  $preferences
     */
    private function preferences(Lecturer $lecturer, array $preferences): JsonResponse
    {
        return $this->response(['lecturer_id' => $lecturer->id, 'preferences' => $preferences]);
    }
}
