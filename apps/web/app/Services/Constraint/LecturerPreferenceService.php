<?php

namespace App\Services\Constraint;

use App\Enums\Constraint\ConstraintCode;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Lecturer;
use Illuminate\Database\DatabaseManager;

/**
 * A preference is "want" (SC_INGIN) or "avoid" (SC_HINDARI) for one time slot. Neutral slots
 * have no row. Saving replaces the whole matrix of the lecturer in one transaction.
 */
readonly class LecturerPreferenceService
{
    public function __construct(
        private LecturerPreference $preference,
        private ConstraintType $constraintType,
        private ConstraintActivityLogService $activityLog,
        private DatabaseManager $db,
    ) {}

    /**
     * @return list<array{time_slot_id: int, type: string}>
     */
    public function getPreferences(Lecturer $lecturer): array
    {
        $types = $this->typeIds();

        return $this->preference->newQuery()
            ->where('lecturer_id', $lecturer->id)
            ->orderBy('time_slot_id')
            ->get()
            ->map(fn (LecturerPreference $row) => [
                'time_slot_id' => $row->time_slot_id,
                'type' => $row->constraint_type_id === $types['want'] ? 'want' : 'avoid',
            ])
            ->all();
    }

    /**
     * @param  list<array{time_slot_id: int|string, type: string}>  $preferences
     * @return list<array{time_slot_id: int, type: string}>
     */
    public function replacePreferences(Lecturer $lecturer, array $preferences): array
    {
        $types = $this->typeIds();
        $before = $this->getPreferences($lecturer);

        $this->db->transaction(function () use ($lecturer, $preferences, $types) {
            $this->preference->newQuery()->where('lecturer_id', $lecturer->id)->delete();

            $this->preference->newQuery()->insert(collect($preferences)->map(fn (array $row) => [
                'lecturer_id' => $lecturer->id,
                'constraint_type_id' => $types[$row['type']],
                'time_slot_id' => (int) $row['time_slot_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });

        $after = $this->getPreferences($lecturer);
        $this->activityLog->preferencesReplaced($lecturer, $before, $after);

        return $after;
    }

    /**
     * @return array{want: int, avoid: int}
     */
    private function typeIds(): array
    {
        $ids = $this->constraintType->newQuery()
            ->whereIn('code', array_map(fn (ConstraintCode $code) => $code->value, ConstraintCode::preferenceCodes()))
            ->pluck('id', 'code');

        return ['want' => (int) $ids[ConstraintCode::SC_INGIN->value], 'avoid' => (int) $ids[ConstraintCode::SC_HINDARI->value]];
    }
}
