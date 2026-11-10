<?php

namespace App\Services\Scheduling\Export;

use Illuminate\Support\Facades\Log;

// HARDCODED for now
final class ExportColors
{
    public const MODE_TPB = 'tpb';
    public const MODE_FACULTY = 'fakultas';
    public const MODE_SUPERADMIN = 'superadmin';

    public const TEXT = '#1A1A1A';
    public const MUTED_TEXT = '#555555';

    public const CELL_BORDER = '#B0B8C8';
    public const CLASH_BORDER = '#DC2626';
    public const FALLBACK = '#E5E7EB';

    public const GROUP_HUES = [
        'TPB' => 215,
        'FSTI' => 160,
        'FPB' => 35,
        'FRTI' => 290,
    ];

    public const MATKUL_COLORS = [
        'Agama Buddha' => ['hex' => '#D3E5EE'],
        'Agama Hindu' => ['hex' => '#B5D4E3'],
        'Agama Islam' => ['hex' => '#97C2D8'],
        'Agama Katolik' => ['hex' => '#D3DEEE'],
        'Agama Kristen' => ['hex' => '#B5C8E3'],
        'Algoritme dan Pemrograman' => ['hex' => '#97B2D8'],
        'Bahasa Indonesia' => ['hex' => '#D3D7EE'],
        'Bahasa Inggris' => ['hex' => '#B5BDE3'],
        'Bahasa Inggris Akademik' => ['hex' => '#97A2D8'],
        'Fisika 1' => ['hex' => '#D3E5EE', 'leftBorder' => '#2E8AB8'],
        'Kewarganegaraan' => ['hex' => '#B5D4E3', 'leftBorder' => '#2E8AB8'],
        'Kewirausahaan Berbasis Teknologi' => ['hex' => '#97C2D8', 'leftBorder' => '#2E8AB8'],
        'Kimia' => ['hex' => '#D3DEEE', 'leftBorder' => '#2E67B8'],
        'Manajemen Sumber Daya' => ['hex' => '#B5C8E3', 'leftBorder' => '#2E67B8'],
        'Matematika 1' => ['hex' => '#97B2D8', 'leftBorder' => '#2E67B8'],
        'Metode Statistika' => ['hex' => '#D3D7EE', 'leftBorder' => '#2E45B8'],
        'Pancasila' => ['hex' => '#B5BDE3', 'leftBorder' => '#2E45B8'],
    ];

    public const PRODI_COLORS = [
        'MA' => ['kelompok' => 'FSTI', 'hex' => '#D3EEDE'],
        'AK' => ['kelompok' => 'FSTI', 'hex' => '#B5E3C8'],
        'ST' => ['kelompok' => 'FSTI', 'hex' => '#97D8B2'],
        'FI' => ['kelompok' => 'FSTI', 'hex' => '#D3EEE5'],
        'IF' => ['kelompok' => 'FSTI', 'hex' => '#B5E3D4'],
        'SI' => ['kelompok' => 'FSTI', 'hex' => '#97D8C2'],
        'BD' => ['kelompok' => 'FSTI', 'hex' => '#D3EEEC'],
        'TE' => ['kelompok' => 'FSTI', 'hex' => '#B5E3DF'],
        'TB' => ['kelompok' => 'FSTI', 'hex' => '#97D8D2'],
        'PK' => ['kelompok' => 'FPB', 'hex' => '#EEDCD3'],
        'KL' => ['kelompok' => 'FPB', 'hex' => '#E3C4B5'],
        'TL' => ['kelompok' => 'FPB', 'hex' => '#D8AD97'],
        'SP' => ['kelompok' => 'FPB', 'hex' => '#EEE3D3'],
        'TT' => ['kelompok' => 'FPB', 'hex' => '#E3D0B5'],
        'SL' => ['kelompok' => 'FPB', 'hex' => '#D8BD97'],
        'PW' => ['kelompok' => 'FPB', 'hex' => '#EEEAD3'],
        'AR' => ['kelompok' => 'FPB', 'hex' => '#E3DBB5'],
        'DK' => ['kelompok' => 'FPB', 'hex' => '#D8CD97'],
        'MS' => ['kelompok' => 'FRTI', 'hex' => '#E3D3EE'],
        'TI' => ['kelompok' => 'FRTI', 'hex' => '#D0B5E3'],
        'LG' => ['kelompok' => 'FRTI', 'hex' => '#BD97D8'],
        'MM' => ['kelompok' => 'FRTI', 'hex' => '#EAD3EE'],
        'TP' => ['kelompok' => 'FRTI', 'hex' => '#DBB5E3'],
        'TK' => ['kelompok' => 'FRTI', 'hex' => '#CD97D8'],
        'RK' => ['kelompok' => 'FRTI', 'hex' => '#EED3EC'],
    ];

    public const GROUP_BASE_COLORS = [
        'TPB' => '#C8D6EA',
        'FSTI' => '#C8EADE',
        'FPB' => '#EADCC8',
        'FRTI' => '#E4C8EA',
    ];

    /** @var array<string, true> */
    private static array $warned = [];

    /**
     * Pure lookup. $entity is a TPB course name (tpb), a study program code (fakultas) or a group
     * code (superadmin). An unknown entity gets grey and one warning in the log.
     *
     * @return array{hex: string, leftBorder: ?string}
     */
    public static function getColor(string $mode, string $entity): array
    {
        $found = match ($mode) {
            self::MODE_TPB => self::MATKUL_COLORS[$entity] ?? null,
            self::MODE_FACULTY => self::PRODI_COLORS[$entity] ?? null,
            self::MODE_SUPERADMIN => isset(self::GROUP_BASE_COLORS[$entity]) ? ['hex' => self::GROUP_BASE_COLORS[$entity]] : null,
            default => null,
        };

        if ($found === null) {
            if (! isset(self::$warned["{$mode}|{$entity}"])) {
                self::$warned["{$mode}|{$entity}"] = true;
                Log::warning("Export colour missing for {$mode} \"{$entity}\", using grey.");
            }

            return ['hex' => self::FALLBACK, 'leftBorder' => null];
        }

        return ['hex' => $found['hex'], 'leftBorder' => $found['leftBorder'] ?? null];
    }
}
