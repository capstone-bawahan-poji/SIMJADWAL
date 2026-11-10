<?php

namespace App\Data\MasterData;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\TpbGroup;
use Spatie\LaravelData\Data;

class TpbGroupFormData extends Data
{
    /**
     * @param  array<int, int|null>  $participants  study_program_id => target semester (null for mengulang)
     * @param  list<int>  $classNumbers
     */
    public function __construct(
        public int $tpbOfferingId,
        public string $code,
        public TpbCohort $cohort,
        public array $participants,
        public array $classNumbers,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?TpbGroup $current = null): self
    {
        $participants = array_key_exists('study_programs', $input)
            ? collect($input['study_programs'])->mapWithKeys(fn (array $row) => [
                (int) $row['id'] => isset($row['target_semester']) ? (int) $row['target_semester'] : null,
            ])->all()
            : collect($current?->participants ?? [])->mapWithKeys(fn (array $row) => [$row['study_program_id'] => $row['target_semester']])->all();

        return new self(
            tpbOfferingId: (int) ($input['tpb_offering_id'] ?? $current?->tpb_offering_id),
            code: $input['code'] ?? $current?->code,
            cohort: isset($input['cohort']) ? TpbCohort::from($input['cohort']) : $current?->cohort,
            participants: $participants,
            classNumbers: array_map('intval', $input['class_numbers'] ?? $current?->class_numbers ?? []),
        );
    }
}
