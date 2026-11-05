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
    private const HEADER_FILL = 'FFE2E8F0';

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
                $lines = array_map(fn (ScheduleDetail $d) => ScheduleLine::course($d)."\n".ScheduleLine::lecturer($d).' · '.ScheduleLine::room($d), $matrix->cell($session, $day));
                if (($time = $matrix->cellTime($session, $day)) && $lines !== []) {
                    array_unshift($lines, $time);
                }
                $sheet->setCellValue([$i + 2, $row], implode("\n\n", $lines));
            }
            $row++;
        }

        $body = "A{$headerRow}:{$lastColumn}".($row - 1);
        $sheet->getStyle($body)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle($body)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A'.($headerRow + 1).':A'.($row - 1))->getFont()->setBold(true);
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
            __('Day') => 10, __('Time') => 13, __('Code') => 12, __('Course') => 36, 'SKS' => 6, __('Class') => 7,
            __('Meeting') => 10, __('Lecturer') => 30, __('Room') => 12, __('Study Program') => 26, __('Semester') => 10, __('Changed manually') => 16,
        ];
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

        $headerRow = $this->heading($sheet, $export, $lastColumn);
        $sheet->fromArray(array_keys($columns), null, "A{$headerRow}");
        $this->styleHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");

        $rows = $export->details->map(fn (ScheduleDetail $d) => [
            $d->timeSlot->day->label(),
            $d->timeSlot->start_time.'–'.$d->timeSlot->end_time,
            $d->course->code,
            $d->course->name,
            $d->course->sks,
            CourseLecturer::labelFor($d->class_number),
            $d->meeting_number,
            ScheduleLine::lecturer($d),
            ScheduleLine::room($d),
            $d->course->studyProgram?->name,
            $d->course->semester,
            $d->is_overridden ? __('Yes') : '',
        ])->all();
        $sheet->fromArray($rows, null, 'A'.($headerRow + 1), true);

        $lastRow = $headerRow + count($rows);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (array_values($columns) as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }
        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastRow}");
        $sheet->freezePane('A'.($headerRow + 1));
        $this->printSetup($sheet, $headerRow);
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
        $style->getFont()->setBold(true);
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
    }
}
