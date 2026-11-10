<?php

namespace Tests\Feature\Scheduling;

use App\Models\MasterData\StudyProgram;
use App\Services\Scheduling\Export\ExportColors;
use Database\Seeders\Reference\FacultyStudyProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportColorsTest extends TestCase
{
    use RefreshDatabase;

    /** Minimum CIE76 distance between two units of one group. Below it only a warning is printed. */
    private const MIN_DELTA_E = 8.0;

    /**
     * @return array<string, list<array{hex: string, leftBorder: ?string}>> group => units, in map order
     */
    private function unitsByGroup(): array
    {
        $groups = ['TPB' => array_values(ExportColors::MATKUL_COLORS)];
        foreach (ExportColors::PRODI_COLORS as $entry) {
            $groups[$entry['kelompok']][] = ['hex' => $entry['hex'], 'leftBorder' => $entry['leftBorder'] ?? null];
        }

        return $groups;
    }

    public function test_every_colour_reads_at_least_7_to_1_against_the_cell_text(): void
    {
        $hexes = array_merge(
            array_column(ExportColors::MATKUL_COLORS, 'hex'),
            array_column(ExportColors::PRODI_COLORS, 'hex'),
            array_values(ExportColors::GROUP_BASE_COLORS),
        );

        foreach ($hexes as $hex) {
            $this->assertGreaterThanOrEqual(7.0, $this->contrast($hex, ExportColors::TEXT), "{$hex} on ".ExportColors::TEXT);
        }
    }

    public function test_no_two_units_of_a_group_look_the_same(): void
    {
        foreach ($this->unitsByGroup() as $group => $units) {
            // From the tenth unit on a colour repeats on purpose, told apart by its left edge.
            $keys = array_map(fn (array $u) => $u['hex'].'|'.($u['leftBorder'] ?? ''), $units);
            $this->assertSame($keys, array_values(array_unique($keys)), "duplicate in {$group}");
            $this->assertSame(array_keys(ExportColors::GROUP_HUES), array_keys(ExportColors::GROUP_BASE_COLORS));
        }
        $this->assertCount(4, array_unique(ExportColors::GROUP_BASE_COLORS));
    }

    public function test_distance_between_units_of_a_group_is_reported(): void
    {
        $warnings = [];
        foreach ($this->unitsByGroup() as $group => $units) {
            $plain = array_values(array_unique(array_column($units, 'hex')));
            foreach ($plain as $i => $a) {
                foreach (array_slice($plain, $i + 1) as $b) {
                    $distance = $this->deltaE($a, $b);
                    $this->assertGreaterThan(0.0, $distance);
                    if ($distance < self::MIN_DELTA_E) {
                        $warnings[$group][] = ['pair' => "{$a}/{$b}", 'distance' => $distance];
                    }
                }
            }
        }

        foreach ($warnings as $group => $pairs) {
            usort($pairs, fn (array $x, array $y) => $x['distance'] <=> $y['distance']);
            fwrite(STDERR, sprintf("\nWARNING %s: %d pairs under ΔE %.0f (closest %s at %.1f)\n", $group, count($pairs), self::MIN_DELTA_E, $pairs[0]['pair'], $pairs[0]['distance']));
        }
    }

    public function test_every_seeded_tpb_course_and_study_program_has_a_colour(): void
    {
        $this->seed(FacultyStudyProgramSeeder::class);

        foreach (glob(database_path('seeders/data/tpb/*.json')) as $path) {
            foreach (json_decode(file_get_contents($path), true)['courses'] as $course) {
                $this->assertArrayHasKey($course['name'], ExportColors::MATKUL_COLORS, "TPB course {$course['name']}");
            }
        }

        foreach (StudyProgram::query()->with('faculty')->get() as $program) {
            $this->assertArrayHasKey($program->code, ExportColors::PRODI_COLORS, "study program {$program->code}");
            $this->assertSame($program->faculty->code, ExportColors::PRODI_COLORS[$program->code]['kelompok']);
        }
    }

    public function test_lookup_is_exact_and_an_unknown_unit_falls_back_to_grey(): void
    {
        $this->assertSame(['hex' => '#D3E5EE', 'leftBorder' => null], ExportColors::getColor(ExportColors::MODE_TPB, 'Agama Buddha'));
        $this->assertSame('#2E8AB8', ExportColors::getColor(ExportColors::MODE_TPB, 'Fisika 1')['leftBorder']);
        $this->assertSame('#C8EADE', ExportColors::getColor(ExportColors::MODE_SUPERADMIN, 'FSTI')['hex']);
        $this->assertSame('#B5E3D4', ExportColors::getColor(ExportColors::MODE_FACULTY, 'IF')['hex']);
        $this->assertSame('#E5E7EB', ExportColors::getColor(ExportColors::MODE_TPB, 'Mata Kuliah Baru')['hex']);
    }

    /** @return array{float, float, float} linear sRGB */
    private function linear(string $hex): array
    {
        return array_map(function (string $pair) {
            $c = hexdec($pair) / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));
    }

    private function luminance(string $hex): float
    {
        [$r, $g, $b] = $this->linear($hex);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private function contrast(string $a, string $b): float
    {
        [$high, $low] = [max($this->luminance($a), $this->luminance($b)), min($this->luminance($a), $this->luminance($b))];

        return ($high + 0.05) / ($low + 0.05);
    }

    /** @return array{float, float, float} CIE L*a*b* (D65) */
    private function lab(string $hex): array
    {
        [$r, $g, $b] = $this->linear($hex);
        $f = fn (float $t) => $t > 0.008856 ? $t ** (1 / 3) : 7.787 * $t + 16 / 116;
        $x = $f((0.4124 * $r + 0.3576 * $g + 0.1805 * $b) / 0.95047);
        $y = $f(0.2126 * $r + 0.7152 * $g + 0.0722 * $b);
        $z = $f((0.0193 * $r + 0.1192 * $g + 0.9505 * $b) / 1.08883);

        return [116 * $y - 16, 500 * ($x - $y), 200 * ($y - $z)];
    }

    private function deltaE(string $a, string $b): float
    {
        return sqrt(array_sum(array_map(fn ($p, $q) => ($p - $q) ** 2, $this->lab($a), $this->lab($b))));
    }
}
