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
 * The TPB file, one row per class meeting. One format for the TPB data import, the TPB schedule
 * import (TPB admin) and the faculty import of the TPB schedule (docs/plans/konvensi-file-tpb.md).
 * Columns, in any order, by header name:
 *
 *   No | Kode MK | Mata Kuliah | SKS | Grup | Peserta | Prodi | Semester | Kelas | Kapasitas
 *      | Dosen Pengampu | Ruangan | Hari | Sesi | Pertemuan
 *
 * The first 11 columns describe the groups and classes (data), the last 4 their place in the
 * schedule. Hari is a day name (Senin..Jumat) or 1-5, Sesi is 1 or "Sesi 1", Pertemuan is 1 or 2
 * (default 1). Dosen is a name (with or without title) or a NIP.
 *
 * Peserta is the kind of group (Maba, Non Maba, ...). Older schedule files used Peserta or
 * "Jumlah Peserta" for the head count: a Peserta column holding only numbers is read as Kapasitas.
 */
class TpbScheduleSheet
{
    public const MAX_ROWS = 3000;

    public const HEADERS = ['No', 'Kode MK', 'Mata Kuliah', 'SKS', 'Grup', 'Peserta', 'Prodi', 'Semester', 'Kelas', 'Kapasitas', 'Dosen Pengampu', 'Ruangan', 'Hari', 'Sesi', 'Pertemuan'];

    private const LABELS = [
        'code' => 'Kode MK', 'name' => 'Mata Kuliah', 'sks' => 'SKS', 'group' => 'Grup', 'cohort' => 'Peserta', 'programs' => 'Prodi',
        'semester' => 'Semester', 'class' => 'Kelas', 'capacity' => 'Kapasitas', 'lecturer' => 'Dosen Pengampu',
        'room' => 'Ruangan', 'day' => 'Hari', 'session' => 'Sesi', 'meeting' => 'Pertemuan',
    ];

    private const ALIASES = [
        'kodemk' => 'code', 'kodematakuliah' => 'code', 'kode' => 'code', 'coursecode' => 'code',
        'namamatakuliah' => 'name', 'matakuliah' => 'name', 'namamk' => 'name', 'coursename' => 'name',
        'sks' => 'sks', 'kredit' => 'sks', 'credits' => 'sks',
        'grup' => 'group', 'gruping' => 'group', 'group' => 'group',
        'peserta' => 'cohort', 'jenispeserta' => 'cohort',
        'prodi' => 'programs', 'kodeprodi' => 'programs',
        'semester' => 'semester',
        'kelas' => 'class', 'class' => 'class',
        'kapasitas' => 'capacity', 'kapasitaskelas' => 'capacity', 'jumlahpeserta' => 'capacity', 'jumlahmahasiswa' => 'capacity',
        'dosen' => 'lecturer', 'dosenpengampu' => 'lecturer', 'namadosen' => 'lecturer', 'nipdosen' => 'lecturer', 'nip' => 'lecturer',
        'ruang' => 'room', 'ruangan' => 'room', 'koderuang' => 'room', 'room' => 'room',
        'hari' => 'day', 'day' => 'day',
        'sesi' => 'session', 'session' => 'session',
        'pertemuan' => 'meeting', 'meeting' => 'meeting',
    ];

    private const DAYS = ['senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5];

    /** @var array<string, int>|null "day:session" => time_slot_id */
    private ?array $slots = null;

    /** @var array<string, Room>|null normalized code => room */
    private ?array $rooms = null;

    /** @var array{full: array<string, list<int>>, name: array<string, list<int>>, nip: array<string, int>}|null */
    private ?array $lecturers = null;

    /** @var array<string, int> field => column index of the last file read */
    private array $columns = [];

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
        $this->columns = $columns;
        $this->requireColumns($required);

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

        return $this->numericPesertaIsCapacity($rows);
    }

    /**
     * Whether the last file read has the column, e.g. has('group') for a file that carries the group data.
     */
    public function has(string $field): bool
    {
        return isset($this->columns[$field]);
    }

    /**
     * @param  list<string>  $fields
     */
    public function requireColumns(array $fields): void
    {
        $missing = array_diff($fields, array_keys($this->columns));
        if ($missing !== []) {
            throw ApiException::validation(['file' => [__('Required columns are missing: :columns.', ['columns' => implode(', ', array_map(fn ($f) => self::LABELS[$f], $missing))])]]);
        }
    }

    /**
     * Old schedule files put the head count under Peserta. A Peserta column that holds only numbers
     * (and no Kapasitas column beside it) is that head count, so it is read as Kapasitas.
     *
     * @param  list<array{row: int, values: array<string, string|null>}>  $rows
     * @return list<array{row: int, values: array<string, string|null>}>
     */
    private function numericPesertaIsCapacity(array $rows): array
    {
        if (! isset($this->columns['cohort']) || isset($this->columns['capacity'])) {
            return $rows;
        }
        $filled = array_filter(array_column(array_column($rows, 'values'), 'cohort'), fn ($v) => $v !== null);
        if ($filled === [] || array_filter($filled, fn ($v) => ! ctype_digit($v)) !== []) {
            return $rows;
        }

        $this->columns['capacity'] = $this->columns['cohort'];
        unset($this->columns['cohort']);

        return array_map(function (array $row) {
            $row['values']['capacity'] = $row['values']['cohort'];
            $row['values']['cohort'] = null;

            return $row;
        }, $rows);
    }

    public function timeSlotId(?string $day, ?string $session): ?int
    {
        $this->slots ??= TimeSlot::query()->get(['id', 'day', 'session'])
            ->mapWithKeys(fn (TimeSlot $slot) => ["{$slot->day->value}:{$slot->session}" => $slot->id])->all();
        $dayNumber = $day === null ? null : (self::DAYS[$this->normalize($day)] ?? (ctype_digit($day) ? (int) $day : null));

        $sessionNumber = $session !== null && preg_match('/^(?:sesi|session)?\s*(\d+)$/i', trim($session), $match) === 1 ? (int) $match[1] : null;

        return $dayNumber === null || $sessionNumber === null ? null : ($this->slots["{$dayNumber}:{$sessionNumber}"] ?? null);
    }

    /**
     * Pertemuan: 1 or 2. Null when the cell is empty, 0 when it is not a number.
     */
    public static function meetingNumber(?string $meeting): ?int
    {
        return $meeting === null ? null : (ctype_digit($meeting) ? (int) $meeting : 0);
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

    /**
     * The blank template of the TPB admin schedule import: the whole format with two example rows.
     */
    public static function template(string $period = ''): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('TPB');
        $sheet->fromArray([
            self::HEADERS,
            [1, 'KU2512015', 'Matematika 1', 3, 'G1', 'Maba', '01,02,16,17', 1, 'A', 40, '', 'E102', 'Senin', 1, 1],
            [2, 'KU2512015', 'Matematika 1', 3, 'G1', '', '', '', 'B', 40, '', 'E103', 'Senin', 1, 1],
        ]);
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $spreadsheet->createSheet()->setTitle('Petunjuk')->fromArray(self::instructions($period), null, 'A1');

        return $spreadsheet;
    }

    /**
     * The "Petunjuk" sheet shared by every TPB template and export.
     *
     * @return list<list<string>>
     */
    public static function instructions(string $period): array
    {
        return [
            ['Periode impor', $period],
            ['Satu baris = satu pertemuan kelas. MK 4 SKS punya dua baris per kelas (Pertemuan 1 dan 2).'],
            ['Grup', 'Baris dengan Kode MK dan Grup yang sama membentuk satu grup. Semua kelas satu grup berada di slot yang sama.'],
            ['Peserta', 'Jenis grup: Maba, Non Maba, Campuran, Mengulang, atau Pilihan. Cukup di baris pertama grup.'],
            ['Prodi', 'Kode prodi dipisah koma. Kosongkan untuk Pilihan. Cukup di baris pertama grup.'],
            ['Semester', 'Semester sasaran. Kosong = otomatis. Kosongkan untuk Mengulang dan Pilihan.'],
            ['Kelas', 'Huruf kelas (A, B, ...). Kosong = berurutan.'],
            ['Kapasitas', 'Jumlah mahasiswa per kelas. Header lama "Jumlah Peserta" juga dibaca di sini.'],
            ['Dosen Pengampu', 'Nama dosen seperti di daftar dosen, dengan atau tanpa gelar.'],
            ['Ruangan', 'Kode ruang bersama, misalnya E101. Kosong bila belum dijadwalkan.'],
            ['Hari', 'Senin sampai Jumat, atau angka 1 sampai 5.'],
            ['Sesi', 'Nomor sesi: 1, atau Sesi 1.'],
            ['Pertemuan', '1 atau 2. Kosong = 1. Pertemuan 2 hanya untuk MK 4 SKS.'],
            ['Mata kuliah yang sudah punya grup', 'Diganti oleh isi file. Mata kuliah yang tidak ada di file tidak diubah.'],
            ['Jadwal', 'Kolom Ruangan, Hari, dan Sesi yang terisi membuat run TPB sumber import. Kolom itu boleh kosong untuk impor data saja.'],
        ];
    }

    /** Lowercase letters and digits only, so punctuation and spacing differences do not matter. */
    private function normalize(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text));
    }
}
