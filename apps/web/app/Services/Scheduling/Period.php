<?php

namespace App\Services\Scheduling;

/**
 * Academic period string YYYY/YYYY-GANJIL|GENAP (validated by PeriodRule).
 * GANJIL schedules odd semesters, GENAP even ones.
 */
final class Period
{
    public static function isOdd(string $period): bool
    {
        return str_ends_with($period, '-GANJIL');
    }

    /**
     * @return list<int>
     */
    public static function semesters(string $period): array
    {
        return self::isOdd($period) ? [1, 3, 5, 7] : [2, 4, 6, 8];
    }
}
