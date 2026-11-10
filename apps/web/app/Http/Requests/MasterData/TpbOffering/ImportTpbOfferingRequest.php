<?php

namespace App\Http\Requests\MasterData\TpbOffering;

use App\Models\MasterData\TpbOffering;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportTpbOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TpbOffering::class);
    }

    public function rules(): array
    {
        return [
            'period' => ['required', new PeriodRule],
            'file' => ['required', 'file', 'extensions:xlsx', 'max:2048'],
            'dry_run' => ['required', 'boolean'],
        ];
    }
}
