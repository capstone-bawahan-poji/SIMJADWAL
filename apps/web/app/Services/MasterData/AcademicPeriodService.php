<?php

namespace App\Services\MasterData;

use App\Data\MasterData\AcademicPeriodData;
use App\Data\MasterData\AcademicPeriodFormData;
use App\Enums\MasterData\ReferenceType;
use App\Models\MasterData\AcademicPeriod;
use Illuminate\Support\Collection;

/**
 * Lecture dates per period. Dates may change at any time (the next .ics export picks them up);
 * the code may not change, nor the row be deleted, while scheduling runs use it.
 */
readonly class AcademicPeriodService
{
    public function __construct(
        private AcademicPeriod $academicPeriod,
        private ReferenceGuard $referenceGuard,
    ) {}

    /**
     * Not paginated: two rows a year. Newest first.
     *
     * @return Collection<int, AcademicPeriodData>
     */
    public function getAcademicPeriods(): Collection
    {
        return $this->academicPeriod->newQuery()
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (AcademicPeriod $period) => AcademicPeriodData::from($period));
    }

    public function getAcademicPeriod(AcademicPeriod $period): AcademicPeriodData
    {
        return AcademicPeriodData::from($period);
    }

    public function createAcademicPeriod(AcademicPeriodFormData $data): AcademicPeriodData
    {
        $period = $this->academicPeriod->newQuery()->create($this->attributes($data));

        return $this->getAcademicPeriod($period->fresh());
    }

    public function updateAcademicPeriod(AcademicPeriod $period, AcademicPeriodFormData $data): AcademicPeriodData
    {
        if ($data->code !== $period->code) {
            $this->ensureUnused($period);
        }

        $period->update($this->attributes($data));

        return $this->getAcademicPeriod($period->fresh());
    }

    public function deleteAcademicPeriod(AcademicPeriod $period): void
    {
        $this->ensureUnused($period);

        $period->delete();
    }

    /**
     * The period with this code, or null when it has no dates yet.
     */
    public function findByCode(string $code): ?AcademicPeriod
    {
        return $this->academicPeriod->newQuery()->where('code', $code)->first();
    }

    private function ensureUnused(AcademicPeriod $period): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::SCHEDULING_RUNS->value => $period->schedulingRuns()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(AcademicPeriodFormData $data): array
    {
        return [
            'code' => $data->code,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
        ];
    }
}
