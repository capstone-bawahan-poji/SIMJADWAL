<?php

namespace App\Services\MasterData\CourseImport;

use App\Exceptions\Shared\ApiException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

final class CourseSheetReader
{
    public const MAX_ROWS = 1000;

    private const HEADERS = [
        'kode mk' => 'code',
        'kode' => 'code',
        'mata kuliah' => 'name',
        'nama mata kuliah' => 'name',
        'sks' => 'sks',
        'semester' => 'semester',
        'kapasitas' => 'class_capacity',
        'kapasitas kelas' => 'class_capacity',
        'kelas' => 'class',
        'dosen pengampu' => 'lecturer',
        'dosen' => 'lecturer',
    ];

    public const LABELS = [
        'code' => 'Kode MK',
        'name' => 'Mata Kuliah',
        'sks' => 'SKS',
        'semester' => 'Semester',
        'class_capacity' => 'Kapasitas',
        'class' => 'Kelas',
        'lecturer' => 'Dosen Pengampu',
    ];

    private const REQUIRED = ['code', 'name', 'sks', 'semester', 'class_capacity', 'class'];

    /**
     * @return list<array{row: int, values: array<string, int|string|null>}> row = Excel row number
     */
    public function read(string $path): array
    {
        $sheet = $this->firstSheet($path);
        $lastRow = $sheet->getHighestDataRow();

        if ($lastRow - 1 > self::MAX_ROWS) {
            throw $this->fileError(__('The file has more than :max data rows.', ['max' => self::MAX_ROWS]));
        }

        $cells = $sheet->rangeToArray('A1:'.$sheet->getHighestDataColumn().$lastRow, null, true, false, false);
        $columns = $this->mapHeader($cells[0] ?? []);

        $rows = [];
        foreach (array_slice($cells, 1) as $index => $cellRow) {
            $values = [];
            foreach ($columns as $field => $column) {
                $values[$field] = $this->clean($cellRow[$column] ?? null);
            }

            if (array_filter($values, fn ($value) => $value !== null) === []) {
                continue;
            }

            $rows[] = ['row' => $index + 2, 'values' => $values + ['lecturer' => null]];
        }

        if ($rows === []) {
            throw $this->fileError(__('The file has no course rows.'));
        }

        return $rows;
    }

    private function firstSheet(string $path): Worksheet
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);

            return $reader->load($path)->getSheet(0);
        } catch (Throwable) {
            throw $this->fileError(__('The file cannot be read. Upload the .xlsx template from the system.'));
        }
    }

    /**
     * @param  array<int, mixed>  $header
     * @return array<string, int> field => column index
     */
    private function mapHeader(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $text) {
            $field = self::HEADERS[mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $text)))] ?? null;
            if ($field !== null && ! isset($columns[$field])) {
                $columns[$field] = $index;
            }
        }

        $missing = array_diff(self::REQUIRED, array_keys($columns));
        if ($missing !== []) {
            throw $this->fileError(__('Required columns are missing: :columns.', [
                'columns' => implode(', ', array_map(fn (string $field) => self::LABELS[$field], $missing)),
            ]));
        }

        return $columns;
    }

    private function clean(mixed $value): int|string|null
    {
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_int($value)) {
            return $value;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    private function fileError(string $message): ApiException
    {
        return ApiException::validation(['file' => [$message]]);
    }
}