<?php

namespace App\Http\Requests\MasterData\TpbOffering;

use App\Models\MasterData\TpbOffering;
use App\Rules\MasterData\Unchanged;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH: course and period never change; delete and create instead.
 */
class UpdateTpbOfferingRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var TpbOffering $offering */
        $offering = $this->route('tpbOffering');

        return [
            'course_id' => ['sometimes', 'integer', new Unchanged($offering->course_id)],
            'period' => ['sometimes', 'string', new Unchanged($offering->period)],
            'parallel_class_count' => ['sometimes', 'required', 'integer', 'between:1,99'],
            'class_capacity' => ['sometimes', 'required', 'integer', 'between:1,1000'],
        ];
    }
}
