<?php

namespace App\Rules\MasterData;

final class CourseRules
{
    /**
     * @param  list<string>  $presence
     * @return array<string, list<string>>
     */
    public static function attributes(array $presence): array
    {
        return [
            'name' => [...$presence, 'string', 'max:150'],
            'sks' => [...$presence, 'integer', 'in:2,3,4'],
            'semester' => [...$presence, 'integer', 'between:1,8'],
            'parallel_class_count' => [...$presence, 'integer', 'between:1,99'],
            'class_capacity' => [...$presence, 'integer', 'between:1,1000'],
        ];
    }
}
