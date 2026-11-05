<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\Account\Role;
use App\Enums\Constraint\ConstraintCode;
use App\Models\MasterData\Faculty;
use App\Permissions\Scheduling\SchedulingPermissions;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchedulingRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(SchedulingPermissions::UPLOAD_RUN);
    }

    public function rules(): array
    {
        return [
            'client_run_id' => ['required', 'uuid'],
            'batch_id' => ['required', 'uuid'],
            'faculty_id' => [
                Rule::requiredIf(fn () => $this->user()->role === Role::SUPER_ADMIN),
                'nullable', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed(),
            ],
            'period' => ['required', new PeriodRule],
            'dataset_fingerprint' => ['required', 'string', 'max:80'],
            'preset_id' => ['nullable', 'integer'],
            'parameter_snapshot' => ['required', 'array'],
            'parameter_snapshot.parallel_class_limit' => ['required', 'integer', 'min:1'],
            'parameter_snapshot.weights' => ['required', 'array'],
            'best_fitness' => ['required', 'numeric'],
            'max_fitness' => ['required', 'numeric', 'gt:0'],
            'best_generation' => ['required', 'integer', 'min:0'],
            'final_generation' => ['required', 'integer', 'min:0', 'gte:best_generation'],
            'fitness_history' => ['required', 'array', 'min:1'],
            'fitness_history.*' => ['numeric'],
            'started_at' => ['required', 'date'],
            'finished_at' => ['required', 'date', 'after_or_equal:started_at'],
            'schedule_details' => ['required', 'array', 'min:1'],
            'schedule_details.*.course_id' => ['required', 'integer'],
            'schedule_details.*.class_number' => ['required', 'integer', 'min:1'],
            'schedule_details.*.meeting_number' => ['required', 'integer', 'in:1,2'],
            'schedule_details.*.lecturer_id' => ['required', 'integer'],
            'schedule_details.*.time_slot_id' => ['required', 'integer'],
            'schedule_details.*.room_id' => ['nullable', 'integer'],
            'violations' => ['present', 'array'],
            'violations.*.detail_index' => ['required', 'integer', 'min:0'],
            'violations.*.constraint_code' => ['required', Rule::enum(ConstraintCode::class)],
            'violations.*.penalty' => ['required', 'numeric', 'min:0'],
            'violations.*.message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function facultyId(): int
    {
        return $this->user()->role === Role::SUPER_ADMIN
            ? (int) $this->validated('faculty_id')
            : (int) $this->user()->faculty_id;
    }
}
