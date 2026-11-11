<?php

namespace App\Services\Scheduling\Import;

use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * The TPB schedule file, one row per class meeting. Read by the TPB admin import (a TPB run) and
 * the faculty import (the faculty's own TPB occupancy). Columns, in any order, by header name:
 *
 *   Kode MK | Nama Mata Kuliah | SKS | Kelas | Hari | Sesi | Ruang | Jumlah Peserta | Dosen
 *
 * Hari is a day name (Senin..Jumat) or 1-5. Dosen is a name (with or without title) or a NIP.
 * Same headers as the TPB schedule import page and the room grid export.
 */
class TpbScheduleSheet
{
    public const MAX_ROWS = 3000;

    public const HEADERS = ['Kode MK', 'Nama Mata Kuliah', 'SKS', 'Kelas', 'Hari', 'Sesi', 'Ruang', 'Jumlah Peserta', 'Dosen'];

    private const ALIASES = [
        'kodemk' => 'code', 'kodematakuliah' => 'code', 'kode' => 'code', 'coursecode' => 'code',
        'namamatakuliah' => 'name', 'matakuliah' => 'name', 'namamk' => 'name', 'coursename' => 'name',
        'sks' => 'sks', 'kredit' => 'sks', 'credits' => 'sks',
        'kelas' => 'class', 'class' => 'class',
        'hari' => 'day', 'day' => 'day',
        'sesi' => 'session', 'session' => 'session',
        'ruang' => 'room', 'ruangan' => 'room', 'koderuang' => 'room', 'room' => 'room',
        'jumlahpeserta' => 'participants', 'peserta' => 'participants', 'kapasitas' => 'participants', 'jumlahmahasiswa' => 'participants',
        'dosen' => 'lecturer', 'dosenpengampu' => 'lecturer', 'namadosen' => 'lecturer', 'nipdosen' => 'lecturer', 'nip' => 'lecturer',
    ];

    private const DAYS = ['senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5];

    /** @var array<string, int>|null "day:session" => time_slot_id */
    private ?array $slots = null;

    /** @var array<string, Room>|null normalized code => room */
    private ?array $rooms = null;

    /** @var array{full: array<string, list<int>>, name: array<string, list<int>>, nip: array<string, int>}|null */
    private ?array $lecturers = null;

    /**
     * @param  list<string>  $required  fields the import cannot do without
     * @return list<array{row: int, values: array<string, string|null>}>
     */
    public function read(string $path, array $required): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (Throwable) {
            throw ApiException::validation(['file' => [__('The file cannot be read. Upload the .xlsx template from the system.')]]);
        }
        $cells = $sheet->rangeToArray('A1:'.$sheet->getHighestDataColumn().$sheet->getHighestDataRow(), null, true, false, false);
        if (count($cells) - 1 > self::MAX_ROWS) {
            throw ApiException::validation(['file' => [__('The file has more than :max data rows.', ['max' => self::MAX_ROWS])]]);
        }

        $columns = [];
        foreach ($cells[0] ?? [] as $index => $text) {
            $field = self::ALIASES[$this->normalize((string) $text)] ?? null;
            if ($field !== null && ! isset($columns[$field])) {
                $columns[$field] = $index;
            }
        }
        $missing = array_diff($required, array_keys($columns));
        if ($missing !== []) {
            $labels = array_combine(['code', 'name', 'sks', 'class', 'day', 'session', 'room', 'participants', 'lecturer'], self::HEADERS);
            throw ApiException::validation(['file' => [__('Required columns are missing: :columns.', ['columns' => implode(', ', array_map(fn ($f) => $labels[$f], $missing))])]]);
        }

        $rows = [];
        foreach (array_slice($cells, 1) as $i => $cellRow) {
            $values = [];
            foreach (array_keys(array_flip(array_values(self::ALIASES))) as $field) {
                $raw = isset($columns[$field]) ? ($cellRow[$columns[$field]] ?? null) : null;
                $text = $raw === null ? '' : trim((string) $raw);
                $values[$field] = $text === '' ? null : (is_float($raw) && floor($raw) === $raw ? (string) (int) $raw : $text);
            }
            if (array_filter($values, fn ($v) => $v !== null) === []) {
                continue;
            }
            $rows[] = ['row' => $i + 2, 'values' => $values];
        }
        if ($rows === []) {
            throw ApiException::validation(['file' => [__('The file has no schedule rows.')]]);
        }

        return $rows;
    }

    public function timeSlotId(?string $day, ?string $session): ?int
    {
        $this->slots ??= TimeSlot::query()->get(['id', 'day', 'session'])
            ->mapWithKeys(fn (TimeSlot $slot) => ["{$slot->day->value}:{$slot->session}" => $slot->id])->all();
        $dayNumber = $day === null ? null : (self::DAYS[$this->normalize($day)] ?? (ctype_digit($day) ? (int) $day : null));

        return $dayNumber === null || $session === null || ! ctype_digit($session) ? null : ($this->slots["{$dayNumber}:{$session}"] ?? null);
    }

    public function room(?string $code): ?Room
    {
        $this->rooms ??= Room::query()->get(['id', 'code', 'name', 'capacity', 'faculty_id'])
            ->mapWithKeys(fn (Room $room) => [$this->normalize($room->code) => $room])->all();

        return $code === null ? null : ($this->rooms[$this->normalize($code)] ?? null);
    }

    /**
     * Name first (with title, then without), NIP as a fallback. Null when not found or ambiguous.
     */
    public function lecturerId(?string $input): ?int
    {
        if ($input === null) {
            return null;
        }
        if ($this->lecturers === null) {
            $this->lecturers = ['full' => [], 'name' => [], 'nip' => []];
            foreach (Lecturer::query()->get(['id', 'nip', 'name', 'title']) as $lecturer) {
                $this->lecturers['nip'][(string) $lecturer->nip] = $lecturer->id;
                $this->lecturers['name'][$this->normalize($lecturer->name)][] = $lecturer->id;
                $this->lecturers['full'][$this->normalize($lecturer->name.' '.$lecturer->title)][] = $lecturer->id;
            }
        }
        $key = $this->normalize($input);
        $ids = $this->lecturers['full'][$key] ?? $this->lecturers['name'][$key] ?? [];

        return count($ids) === 1 ? $ids[0] : ($ids === [] ? ($this->lecturers['nip'][trim($input)] ?? null) : null);
    }

    public static function classNumber(?string $label): ?int
    {
        if ($label === null || $label === '' || $label === '_') {
            return $label === '_' ? 1 : null;
        }
        if (ctype_digit($label)) {
            return (int) $label;
        }

        return ctype_alpha($label) ? array_reduce(str_split(strtoupper($label)), fn (int $n, string $c) => $n * 26 + (ord($c) - 64), 0) : null;
    }

    public static function template(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal TPB');
        $sheet->fromArray([self::HEADERS, ['KU2512015', 'Matematika 1', 3, 'A', 'Senin', 1, 'E102', 38, '']]);
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /** Lowercase letters and digits only, so punctuation and spacing differences do not matter. */
    private function normalize(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text));
    }
}
