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
     * "2026/2027-GANJIL" => "Ganjil 2026/2027".
     */
    public static function label(string $period): string
    {
        [$years, $term] = explode('-', $period, 2);

        return ucfirst(strtolower($term)).' '.$years;
    }

    /**
     * @return list<int>
     */
    public static function semesters(string $period): array
    {
        return self::isOdd($period) ? [1, 3, 5, 7] : [2, 4, 6, 8];
    }
}
