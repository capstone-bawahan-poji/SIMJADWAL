<?php

namespace App\Http\Requests\MasterData\TpbGroup;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\TpbGroup;
use App\Models\MasterData\TpbOffering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules that need the offering (class count, semester parity, programs in two groups) live in TpbGroupService.
 */
class StoreTpbGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tpb_offering_id' => ['required', 'integer', Rule::exists(TpbOffering::class, 'id')],
            'code' => ['required', 'string', 'max:20', Rule::unique(TpbGroup::class)->where('tpb_offering_id', $this->integer('tpb_offering_id'))],
            ...TpbGroupRules::attributes(['required'], TpbCohort::tryFrom((string) $this->input('cohort'))),
        ];
    }
}
