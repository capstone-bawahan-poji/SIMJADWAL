<?php

namespace Tests\Unit\MasterData;

use App\Services\MasterData\CourseImport\LecturerNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LecturerNameNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function names(): array
    {
        return [
            'titles after comma' => ['Ramadhan Paninggalih S.Si., M.Si., M.Sc.', 'ramadhan paninggalih'],
            'comma before titles' => ['Darmansyah, S.Si., M.T.I', 'darmansyah'],
            'titles without comma' => ['Nur Fajri Azhar S.Kom., M.Kom.', 'nur fajri azhar'],
            'title with a space' => ['Muchammad Chandra Cahyo Utomo, S. Kom., M.Kom.', 'muchammad chandra cahyo utomo'],
            'doctorate' => ['Bima Prihasto, S.Si., M.Si., Ph.D.', 'bima prihasto'],
            'prefix titles' => ['Prof. Dr. Ir. Budi Santoso, M.T.', 'budi santoso'],
            'case and spacing' => ['  RIZKY   amelia ', 'rizky amelia'],
            'abbreviated name part kept' => ['Muh. Rizki, S.T.', 'muh rizki'],
            'plain name' => ['Yustina Fitriani', 'yustina fitriani'],
            'digits kept' => ['Dosen Informatika 01', 'dosen informatika 01'],
        ];
    }

    #[DataProvider('names')]
    public function test_normalizes_name(string $input, string $expected): void
    {
        $this->assertSame($expected, LecturerNameNormalizer::normalize($input));
    }
}
