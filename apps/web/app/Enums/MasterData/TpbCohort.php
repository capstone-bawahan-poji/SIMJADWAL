<?php

namespace App\Enums\MasterData;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who attends a TPB group. Regular cohorts (maba, non_maba, campuran) block the slot hard for the
 * programs at their target semester. mengulang only asks the faculty GA to leave one parallel class
 * of every program course free (SC_ULANG). pilihan is open to every program and blocks nothing.
 */
#[TypeScript]
enum TpbCohort: string
{
    case MABA = 'maba';
    case NON_MABA = 'non_maba';
    case CAMPURAN = 'campuran';
    case MENGULANG = 'mengulang';
    case PILIHAN = 'pilihan';

    public function label(): string
    {
        return match ($this) {
            self::MABA => __('New students'),
            self::NON_MABA => __('Non-new students'),
            self::CAMPURAN => __('New and non-new students'),
            self::MENGULANG => __('Retake'),
            self::PILIHAN => __('Elective'),
        };
    }

    public function isRegular(): bool
    {
        return in_array($this, [self::MABA, self::NON_MABA, self::CAMPURAN], true);
    }

    /**
     * Regular cohorts list programs with a target semester, mengulang lists programs only, pilihan lists none.
     */
    public function hasPrograms(): bool
    {
        return $this !== self::PILIHAN;
    }

    /**
     * @return list<self>
     */
    public static function regular(): array
    {
        return array_values(array_filter(self::cases(), fn (self $cohort) => $cohort->isRegular()));
    }
}