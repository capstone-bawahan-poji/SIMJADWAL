<?php

namespace App\Services\Scheduling;

use App\Enums\Constraint\ConstraintCode;
use App\Enums\Scheduling\RunStatus;
use App\Exceptions\Shared\ApiException;
use App\Models\Constraint\ConstraintType;
use App\Models\MasterData\Faculty;
use App\Models\Scheduling\GaParameterPreset;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\Scheduling\ViolationLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

readonly class SchedulingRunUploadService
{
    private const CHUNK = 500;

    public function __construct(
        private DatabaseManager $db,
        private SchedulingDatasetService $datasetService,
        private ScheduleIntegrityChecker $checker,
    ) {}

    /**
     * @param  array<string, mixed>  $v  validated StoreSchedulingRunRequest
     * @return array{run: SchedulingRun, created: bool}
     */
    public function store(User $actor, Faculty $faculty, array $v): array
    {
        $existing = SchedulingRun::query()->where('client_run_id', $v['client_run_id'])->first();
        if ($existing !== null) {
            if ($existing->faculty_id !== $faculty->id) {
                throw ApiException::validation(['client_run_id' => [__('validation.unique', ['attribute' => 'client_run_id'])]]);
            }

            return ['run' => $existing, 'created' => false];
        }

        if (isset($v['preset_id']) && ! GaParameterPreset::query()->whereKey($v['preset_id'])->where('faculty_id', $faculty->id)->exists()) {
            throw ApiException::validation(['preset_id' => [__('The preset does not belong to this faculty.')]]);
        }

        $dataset = $this->datasetService->build($faculty, $v['period']);
        if (! hash_equals($dataset['fingerprint'], $v['dataset_fingerprint'])) {
            throw ApiException::datasetChanged();
        }

        $details = array_values($v['schedule_details']);
        $check = $this->checker->check($dataset, $details, (int) $v['parameter_snapshot']['parallel_class_limit']);
        $errors = $check['errors'];

        $reported = array_count_values(array_column($v['violations'], 'constraint_code'));
        foreach (['HC2', 'HC3', 'HC4'] as $code) {
            if (($reported[$code] ?? 0) !== $check['hard'][$code]) {
                $errors["violations.{$code}"] = [__('The reported :code count (:reported) differs from the server count (:counted).', [
                    'code' => $code, 'reported' => $reported[$code] ?? 0, 'counted' => $check['hard'][$code],
                ])];
            }
        }
        foreach ($v['violations'] as $i => $violation) {
            if ($violation['detail_index'] >= count($details)) {
                $errors["violations.{$i}.detail_index"] = [__('The violation points to a schedule row that does not exist.')];
            }
        }
        if ($errors !== []) {
            throw ApiException::validation($errors);
        }

        $run = $this->db->transaction(fn () => $this->persist($actor, $faculty, $v, $details));
        activity('scheduling')->performedOn($run)->event('scheduling_run.uploaded')
            ->log(__('Uploaded scheduling run :id', ['id' => $run->id]));

        return ['run' => $run, 'created' => true];
    }

    /**
     * @param  array<string, mixed>  $v
     * @param  list<array<string, int|null>>  $details
     */
    private function persist(User $actor, Faculty $faculty, array $v, array $details): SchedulingRun
    {
        $snapshot = $v['parameter_snapshot'];

        $run = SchedulingRun::query()->create([
            'client_run_id' => $v['client_run_id'],
            'batch_id' => $v['batch_id'],
            'faculty_id' => $faculty->id,
            'period' => $v['period'],
            'preset_id' => $v['preset_id'] ?? null,
            'dataset_fingerprint' => $v['dataset_fingerprint'],
            'parameter_snapshot' => $snapshot,
            'status' => RunStatus::DONE,
            'current_generation' => $v['final_generation'],
            'current_fitness' => $v['best_fitness'],
            'best_fitness' => $v['best_fitness'],
            'max_fitness' => $v['max_fitness'],
            'best_generation' => $v['best_generation'],
            'fitness_history' => array_values($v['fitness_history']),
            'is_active' => false,
            'triggered_by' => $actor->id,
            'started_at' => CarbonImmutable::parse($v['started_at']),
            'finished_at' => CarbonImmutable::parse($v['finished_at']),
        ]);

        foreach (array_chunk($details, self::CHUNK) as $chunk) {
            ScheduleDetail::query()->insert(array_map(fn (array $d) => [
                'scheduling_run_id' => $run->id,
                'course_id' => $d['course_id'],
                'class_number' => $d['class_number'],
                'meeting_number' => $d['meeting_number'],
                'lecturer_id' => $d['lecturer_id'],
                'time_slot_id' => $d['time_slot_id'],
                'room_id' => $d['room_id'] ?? null,
            ], $chunk));
        }

        $idByKey = ScheduleDetail::query()->where('scheduling_run_id', $run->id)
            ->get(['id', 'course_id', 'class_number', 'meeting_number'])
            ->mapWithKeys(fn (ScheduleDetail $d) => ["{$d->course_id}:{$d->class_number}:{$d->meeting_number}" => $d->id]);
        $typeIds = ConstraintType::query()->get(['id', 'code'])->mapWithKeys(fn (ConstraintType $t) => [$t->code->value => $t->id]);

        $rows = array_map(function (array $violation) use ($run, $details, $idByKey, $typeIds) {
            $d = $details[$violation['detail_index']];

            return [
                'scheduling_run_id' => $run->id,
                'schedule_detail_id' => $idByKey["{$d['course_id']}:{$d['class_number']}:{$d['meeting_number']}"] ?? null,
                'constraint_type_id' => $typeIds[ConstraintCode::from($violation['constraint_code'])->value],
                'penalty' => $violation['penalty'],
                'message' => isset($violation['message']) ? Str::limit($violation['message'], 500, '') : null,
            ];
        }, array_values($v['violations']));

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            ViolationLog::query()->insert($chunk);
        }

        return $run;
    }
}
