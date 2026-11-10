<?php

namespace App\Services\MasterData;

use App\Data\MasterData\TpbOfferingData;
use App\Data\MasterData\TpbOfferingFormData;
use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\TpbOffering;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;

/**
 * TPB courses opened per period. Every TPB admin manages every offering.
 */
readonly class TpbOfferingService
{
    public function __construct(
        private TpbOffering $tpbOffering,
        private ReferenceGuard $referenceGuard,
        private DatabaseManager $db,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TpbOfferingData>
     */
    public function getOfferings(?string $period, ?int $courseId, ?string $search, int $perPage): LengthAwarePaginator
    {
        return TpbOfferingData::prepareQuery($this->tpbOffering->newQuery())
            ->when($period, fn (Builder $query) => $query->where('period', $period))
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->when($search, fn (Builder $query) => $query->whereHas('course', fn (Builder $query) => $query
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")))
            ->orderByDesc('period')
            ->orderBy(fn ($query) => $query->select('name')->from('courses')->whereColumn('courses.id', 'tpb_offerings.course_id'))
            ->orderBy('course_id')
            ->paginate($perPage)
            ->through(fn (TpbOffering $offering) => TpbOfferingData::from($offering));
    }

    public function getOffering(TpbOffering $offering): TpbOfferingData
    {
        return TpbOfferingData::from(TpbOfferingData::loadRelations($offering));
    }

    public function createOffering(TpbOfferingFormData $data): TpbOfferingData
    {
        $offering = $this->tpbOffering->newQuery()->create($this->attributes($data));

        return $this->getOffering($offering);
    }

    /**
     * Course and period never change: UpdateTpbOfferingRequest prohibits them.
     */
    public function updateOffering(TpbOffering $offering, TpbOfferingFormData $data): TpbOfferingData
    {
        $highest = (int) $offering->courseLecturers()->max('class_number');
        if ($data->parallelClassCount < $highest) {
            throw ApiException::validation(['parallel_class_count' => [__('Class :class is still in a group. Remove it from its group first.', [
                'class' => CourseLecturer::labelFor($highest),
            ])]]);
        }

        $offering->update($this->attributes($data));

        return $this->getOffering($offering->fresh());
    }

    public function deleteOffering(TpbOffering $offering): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::TPB_GROUPS->value => $offering->groups()->count(),
        ]);

        $offering->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(TpbOfferingFormData $data): array
    {
        return [
            'course_id' => $data->courseId,
            'period' => $data->period,
            'parallel_class_count' => $data->parallelClassCount,
            'class_capacity' => $data->classCapacity,
        ];
    }
}
