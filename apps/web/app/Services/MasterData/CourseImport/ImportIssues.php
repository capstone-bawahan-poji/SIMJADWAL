<?php

namespace App\Services\MasterData\CourseImport;

final class ImportIssues
{
    /** @var list<array{row: int, column: string, value: int|string|null, message: string}> */
    private array $errors = [];

    /** @var list<array{row: int, column: string, message: string}> */
    private array $warnings = [];

    public function error(int $row, string $field, int|string|null $value, string $message): void
    {
        $this->errors[] = ['row' => $row, 'column' => CourseSheetReader::LABELS[$field], 'value' => $value, 'message' => $message];
    }

    public function warning(int $row, string $field, string $message): void
    {
        $this->warnings[] = ['row' => $row, 'column' => CourseSheetReader::LABELS[$field], 'message' => $message];
    }

    /**
     * @param  list<int>  $rows
     */
    public function hasErrorIn(array $rows): bool
    {
        return collect($this->errors)->contains(fn (array $error) => in_array($error['row'], $rows, true));
    }

    /**
     * @return list<array{row: int, column: string, value: int|string|null, message: string}>
     */
    public function errors(): array
    {
        return collect($this->errors)->sortBy('row')->values()->all();
    }

    /**
     * @return list<array{row: int, column: string, message: string}>
     */
    public function warnings(): array
    {
        return collect($this->warnings)->sortBy('row')->values()->all();
    }
}