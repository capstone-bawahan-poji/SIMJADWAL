<?php

namespace App\Data\Account;

use App\Data\BaseData;
use App\Models\Account\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class ActivityLogData extends BaseData
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public int $id,
        public ?string $event,
        public string $description,
        public ?UserSummaryData $causer,
        public ?string $subjectType,
        public ?int $subjectId,
        public array $changes,
        public array $properties,
        public ?CarbonImmutable $createdAt,
    ) {}

    public static function relations(): array
    {
        return ['causer'];
    }

    public static function fromModel(ActivityLog $activityLog): self
    {
        return new self(
            id: $activityLog->id,
            event: $activityLog->event,
            description: $activityLog->description,
            causer: $activityLog->causer ? UserSummaryData::from($activityLog->causer) : null,
            subjectType: $activityLog->subject_type ? Str::snake(class_basename($activityLog->subject_type)) : null,
            subjectId: $activityLog->subject_id === null ? null : (int) $activityLog->subject_id,
            changes: $activityLog->attribute_changes?->toArray() ?? [],
            properties: $activityLog->properties?->toArray() ?? [],
            createdAt: $activityLog->created_at?->toImmutable(),
        );
    }
}
