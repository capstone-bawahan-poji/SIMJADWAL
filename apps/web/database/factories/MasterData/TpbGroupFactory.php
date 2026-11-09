<?php

namespace Database\Factories\MasterData;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\TpbGroup;
use App\Models\MasterData\TpbOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TpbGroup>
 */
class TpbGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tpb_offering_id' => TpbOffering::factory(),
            'code' => fake()->unique()->regexify('G[0-9]{3}'),
            'cohort' => TpbCohort::MABA,
        ];
    }
}
