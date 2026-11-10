<?php

namespace App\Rules\MasterData;

final class CourseRules
{
    /**
     * TPB courses carry no semester, class count or capacity: those belong to each TPB offering.
     *
     * @param  list<string>  $presence
     * @return array<string, list<string>>
     */
    public static function attributes(array $presence, bool $isTpb = false): array
    {
        $regular = fn (array $rules) => $isTpb ? ['prohibited'] : [...$presence, ...$rules];

        return [
            'name' => [...$presence, 'string', 'max:150'],
            'sks' => [...$presence, 'integer', 'in:2,3,4'],
            'semester' => [...$presence, 'integer', 'between:1,8'],
            'parallel_class_count' => [...$presence, 'integer', 'between:1,99'],
            'class_capacity' => [...$presence, 'integer', 'between:1,1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
