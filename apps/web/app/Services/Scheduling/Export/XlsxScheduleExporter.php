<?php

namespace App\Services\Scheduling\Export;

use App\Models\MasterData\CourseLecturer;
use App\Models\Scheduling\ScheduleDetail;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Two sheets: "Matriks" (session x weekday, like the schedule page) for reading and printing,
 * "Daftar" (one row per meeting, frozen header, autofilter) for sorting and filtering.
 */
class XlsxScheduleExporter implements ScheduleExporter
{
    private const HEADER_FILL = 'FF1E3A8A';

    private const BORDER_COLOR = 'FFCBD5E1';

    private const SESSION_FILL = 'FFF1F5F9';

    public function extension(): string
    {
        return 'xlsx';
    }

    public function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function render(ScheduleExport $export): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle($export->title)->setCreator('SIMJADWAL');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->matrixSheet($book->getActiveSheet(), $export);
        $this->listSheet($book->createSheet(), $export);
        $book->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'jadwal');
        try {
            (new Xlsx($book))->save($path);

            return file_get_contents($path);
        } finally {
            @unlink($path);
            $book->disconnectWorksheets();
        }
    }

    private function matrixSheet(Worksheet $sheet, ScheduleExport $export): void
    {
        $sheet->setTitle('Matriks');
        $matrix = $export->matrix();
        $lastColumn = Coordinate::stringFromColumnIndex(count($matrix->days) + 1);

        $headerRow = $this->heading($sheet, $export, $lastColumn);
        $sheet->setCellValue("A{$headerRow}", __('Session'));
        foreach ($matrix->days as $i => $day) {
            $sheet->setCellValue([$i + 2, $headerRow], $day->label());
        }
        $this->styleHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");

        $row = $headerRow + 1;
        foreach ($matrix->sessions as $session => $range) {
            $sheet->setCellValue("A{$row}", __('Session').' '.$session."\n".$range);
            foreach ($matrix->days as $i => $day) {
                $lines = array_map(
                    fn (array $group) => $group['title']."\n".implode("\n", array_map(ScheduleLine::classLine(...), $group['details'])),
                    ScheduleLine::groupByCourse($matrix->cell($session, $day)),
                );
                if (($time = $matrix->cellTime($session, $day)) && $lines !== []) {
                    array_unshift($lines, $time);
                }
                $sheet->setCellValue([$i + 2, $row], implode("\n\n", $lines));
            }
            $row++;
        }

        $body = "A{$headerRow}:{$lastColumn}".($row - 1);
        $sheet->getStyle($body)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $this->gridBorders($sheet, $body);
        $sessionColumn = $sheet->getStyle('A'.($headerRow + 1).':A'.($row - 1));
        $sessionColumn->getFont()->setBold(true);
        $sessionColumn->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::SESSION_FILL);
        $sheet->getColumnDimension('A')->setWidth(14);
        foreach (range(2, count($matrix->days) + 1) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setWidth(34);
        }
        $sheet->freezePane('B'.($headerRow + 1));
        $this->printSetup($sheet, $headerRow);
    }

    private function listSheet(Worksheet $sheet, ScheduleExport $export): void
    {
        $sheet->setTitle('Daftar');
        $columns = [
            [__('Day'), 10, fn (ScheduleDetail $d) => $d->timeSlot->day->label()],
            [__('Time'), 13, fn (ScheduleDetail $d) => $d->timeSlot->start_time.'–'.$d->timeSlot->end_time],
            [__('Code'), 12, fn (ScheduleDetail $d) => $d->course->code],
            [__('Course'), 36, fn (ScheduleDetail $d) => $d->course->name],
            ['SKS', 6, fn (ScheduleDetail $d) => $d->course->sks],
            [__('Class'), 7, fn (ScheduleDetail $d) => CourseLecturer::labelFor($d->class_number)],
            [__('Meeting'), 10, fn (ScheduleDetail $d) => $d->meeting_number],
            [__('Lecturer'), 30, fn (ScheduleDetail $d) => ScheduleLine::lecturer($d)],
            [__('Room'), 12, fn (ScheduleDetail $d) => ScheduleLine::room($d)],
            [__('Study Program'), 26, fn (ScheduleDetail $d) => $d->course->studyProgram?->name],
            [__('Semester'), 10, fn (ScheduleDetail $d) => $d->course->semester],
            [__('Changed manually'), 16, fn (ScheduleDetail $d) => $d->is_overridden ? __('Yes') : null],
        ];

        $rows = $export->details->map(fn (ScheduleDetail $d) => array_map(fn (array $column) => $column[2]($d), $columns))->all();
        // Columns that are blank on every row (TPB has no study program or semester) are left out.
        $kept = array_keys(array_filter($columns, fn (array $column, int $i) => collect($rows)->contains(fn (array $row) => $row[$i] !== null && $row[$i] !== ''), ARRAY_FILTER_USE_BOTH));
        $columns = array_values(array_intersect_key($columns, array_flip($kept)));
        $rows = array_map(fn (array $row) => array_values(array_intersect_key($row, array_flip($kept))), $rows);
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

        $headerRow = $this->heading($sheet, $export, $lastColumn);
        $sheet->fromArray(array_column($columns, 0), null, "A{$headerRow}");
        $this->styleHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");
        $sheet->fromArray($rows, null, 'A'.($headerRow + 1), true);

        $lastRow = $headerRow + count($rows);
        $this->gridBorders($sheet, "A{$headerRow}:{$lastColumn}{$lastRow}");
        foreach (array_column($columns, 1) as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }
        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastRow}");
        $sheet->freezePane('A'.($headerRow + 1));
        $this->printSetup($sheet, $headerRow);
    }

    private function gridBorders(Worksheet $sheet, string $range): void
    {
        $borders = $sheet->getStyle($range)->getBorders()->getAllBorders();
        $borders->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::BORDER_COLOR);
    }

    /**
     * Title lines above the table. Returns the row of the table header.
     */
    private function heading(Worksheet $sheet, ScheduleExport $export, string $lastColumn): int
    {
        $sheet->setCellValue('A1', $export->title);
        $sheet->setCellValue('A2', $export->subtitle);
        $sheet->setCellValue('A3', __('Printed :date', ['date' => $export->generatedAt->translatedFormat('j F Y H:i')]));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->getColor()->setARGB('FF475569');
        foreach ([1, 2, 3] as $row) {
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        }

        if (! $export->isDraft) {
            return 5;
        }

        $sheet->setCellValue('A4', __('DRAFT — not published yet'));
        $sheet->mergeCells("A4:{$lastColumn}4");
        $sheet->getStyle('A4')->getFont()->setBold(true)->getColor()->setARGB('FFB91C1C');

        return 6;
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function printSetup(Worksheet $sheet, int $headerRow): void
    {
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getHeaderFooter()->setOddFooter('&L&8SIMJADWAL&R&8Halaman &P dari &N');
    }
}
