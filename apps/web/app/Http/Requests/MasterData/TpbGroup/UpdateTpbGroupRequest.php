<?php

namespace App\Http\Requests\MasterData\TpbGroup;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\TpbGroup;
use App\Rules\MasterData\Unchanged;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH: every field is optional. study_programs and class_numbers replace the whole list.
 * Changing the cohort between regular and mengulang needs study_programs again, because the
 * target semester follows the cohort.
 */
class UpdateTpbGroupRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var TpbGroup $group */
        $group = $this->route('tpbGroup');
        $cohort = $this->has('cohort') ? TpbCohort::tryFrom((string) $this->input('cohort')) : $group->cohort;

        return [
            'tpb_offering_id' => ['sometimes', 'integer', new Unchanged($group->tpb_offering_id)],
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique(TpbGroup::class)->where('tpb_offering_id', $group->tpb_offering_id)->ignore($group)],
            ...TpbGroupRules::attributes(['sometimes', 'required'], $cohort),
        ];
    }
}
