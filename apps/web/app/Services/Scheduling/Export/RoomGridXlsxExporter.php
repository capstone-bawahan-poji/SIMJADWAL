<?php

namespace App\Services\Scheduling\Export;

use App\Models\MasterData\TimeSlot;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Sheet "Ruangan": time slots down, buildings and rooms across (as the TPB spreadsheets are laid out).
 * Cells are coloured by origin or entity (RoomGrid::fill()); a clash is red.
 */
class RoomGridXlsxExporter implements RoomGridExporter
{
    private const HEADER_FILL = 'FF1E3A8A';

    private const DAY_FILL = 'FFE2E8F0';

    /** Every room cell is the same size, whatever it holds. */
    private const ROW_HEIGHT = 46;

    private const COLUMN_WIDTH = 24;

    public function extension(): string
    {
        return 'xlsx';
    }

    public function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function render(RoomGridExport $export): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle($export->title)->setCreator('SIMJADWAL');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->gridSheet($book->getActiveSheet(), $export);

        $path = tempnam(sys_get_temp_dir(), 'grid');
        try {
            (new Xlsx($book))->save($path);

            return file_get_contents($path);
        } finally {
            @unlink($path);
            $book->disconnectWorksheets();
        }
    }

    private function gridSheet(Worksheet $sheet, RoomGridExport $export): void
    {
        $sheet->setTitle(__('Rooms'));
        $grid = $export->grid;
        $roomCount = array_sum(array_map('count', $grid->buildings));
        $lastColumn = Coordinate::stringFromColumnIndex($roomCount + 2);

        $sheet->setCellValue('A1', $export->title);
        $sheet->setCellValue('A2', $export->subtitle);
        $sheet->setCellValue('A3', __('Printed :date', ['date' => $export->generatedAt->translatedFormat('j F Y H:i')]));
        $this->legend($sheet, $grid);
        $this->badge($sheet, $grid);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->getColor()->setARGB('FF475569');

        $buildingRow = 6;
        $roomRow = 7;
        $sheet->setCellValue("A{$roomRow}", __('Day'));
        $sheet->setCellValue("B{$roomRow}", __('Time'));
        $sheet->mergeCells("A{$buildingRow}:B{$buildingRow}");

        $column = 3;
        $roomColumns = [];
        foreach ($grid->buildings as $building => $rooms) {
            $first = $column;
            foreach ($rooms as $room) {
                $sheet->setCellValue([$column, $roomRow], $room->code);
                $roomColumns[$room->id] = $column++;
            }
            $sheet->setCellValue([$first, $buildingRow], mb_strtoupper(RoomGrid::buildingLabel($building)));
            if ($column - 1 > $first) {
                $sheet->mergeCells([$first, $buildingRow, $column - 1, $buildingRow]);
            }
        }
        $this->header($sheet, "A{$buildingRow}:{$lastColumn}{$roomRow}");

        $row = $roomRow + 1;
        $day = null;
        $edges = [];
        foreach ($grid->timeSlots as $slot) {
            /** @var TimeSlot $slot */
            if ($slot->day !== $day) {
                $day = $slot->day;
                $sheet->setCellValue("A{$row}", $day->label());
                $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                $style = $sheet->getStyle("A{$row}:{$lastColumn}{$row}");
                $style->getFont()->setBold(true);
                $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::DAY_FILL);
                $row++;
            }

            $sheet->getRowDimension($row)->setRowHeight(self::ROW_HEIGHT);
            $sheet->setCellValue("A{$row}", __('Session').' '.$slot->session);
            $sheet->setCellValue("B{$row}", str_replace('–', ' - ', ScheduleMatrix::range($slot)));
            foreach ($grid->buildings as $rooms) {
                foreach ($rooms as $room) {
                    $genes = $grid->cell($slot, $room);
                    if ($genes === []) {
                        continue;
                    }
                    $cell = Coordinate::stringFromColumnIndex($roomColumns[$room->id]).$row;
                    $sheet->setCellValue($cell, $grid->text($genes));
                    $color = $grid->color($slot, $room);
                    $this->paint($sheet, $cell, $color);
                    $edges[] = [$cell, $color, $grid->isClash($slot, $room)];
                }
            }
            $row++;
        }

        $body = "A{$buildingRow}:{$lastColumn}".($row - 1);
        $sheet->getStyle($body)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF'.ltrim(ExportColors::CELL_BORDER, '#'));
        
        foreach ($edges as [$cell, $color, $clash]) {
            $this->edge($sheet, $cell, $color, $clash);
        }
        $bodyStyle = $sheet->getStyle('A'.($roomRow + 1).':'.$lastColumn.($row - 1));
        $bodyStyle->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $bodyStyle->getFont()->setSize(8);
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(15);
        for ($i = 3; $i <= $roomCount + 2; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setWidth(self::COLUMN_WIDTH);
        }
        $sheet->freezePane('C'.($roomRow + 1));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)
            ->setFitToWidth(0)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($buildingRow, $roomRow)->setColumnsToRepeatAtLeft(['A', 'B']);
    }

    /**
     * What the colours mean, one coloured cell per entry from the first room column.
     */
    private function legend(Worksheet $sheet, RoomGrid $grid): void
    {
        $column = 3;
        foreach ($grid->legend as $entry) {
            $cell = Coordinate::stringFromColumnIndex($column).'4';
            $sheet->setCellValue($cell, $entry['label']);
            $this->paint($sheet, $cell, $entry);
            $this->edge($sheet, $cell, $entry, false);
            $style = $sheet->getStyle($cell);
            $style->getFont()->setBold(true);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $column++;
        }
        $sheet->getRowDimension(4)->setRowHeight(32);
        $sheet->setCellValue('A4', __('Colour'));
        $sheet->getStyle('A4')->getFont()->setBold(true);
    }

    /**
     * Fill and text. Text is always #1A1A1A.
     *
     * @param  array{hex: string, leftBorder: ?string}|null  $color
     */
    private function paint(Worksheet $sheet, string $cell, ?array $color): void
    {
        $style = $sheet->getStyle($cell);
        $style->getFont()->setSize(8)->getColor()->setARGB('FF1A1A1A');
        $style->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        if ($color !== null) {
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.ltrim($color['hex'], '#'));
        }
    }

    /**
     * Left edge for the tenth unit of a group on, or a red frame for a clash (the frame wins).
     *
     * @param  array{hex: string, leftBorder: ?string}|null  $color
     */
    private function edge(Worksheet $sheet, string $cell, ?array $color, bool $clash): void
    {
        $style = $sheet->getStyle($cell);
        if ($clash) {
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF'.ltrim(ExportColors::CLASH_BORDER, '#'));
        } elseif ($color['leftBorder'] ?? null) {
            $style->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK)->getColor()->setARGB('FF'.ltrim($color['leftBorder'], '#'));
        }
    }

    /**
     * Small status badge under the legend: green when clean, red with the count otherwise.
     */
    private function badge(Worksheet $sheet, RoomGrid $grid): void
    {
        $clean = $grid->clashCount() === 0;
        $sheet->setCellValue('A5', $clean ? __('No clash') : __(':count clashes', ['count' => $grid->clashCount()]));
        $sheet->mergeCells('A5:B5');
        $style = $sheet->getStyle('A5:B5');
        $style->getFont()->setSize(8)->setBold(true)->getColor()->setARGB($clean ? 'FF166534' : 'FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($clean ? 'FFDCFCE7' : 'FFDC2626');
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function header(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }
}
