<?php

namespace App\Services\MasterData\CourseImport;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One sheet only. Drop-downs use inline lists, never ranges on other sheets: Numbers and
 * some other apps cannot import those. Lecturer names are typed and matched on import.
 */
final class CourseImportTemplate
{
    public const HEADERS = ['No', 'Kode MK', 'Mata Kuliah', 'SKS', 'Semester', 'Kapasitas', 'Kelas', 'Dosen Pengampu'];
    private const LAST_ROW = CourseSheetReader::MAX_ROWS + 1;

    public function build(StudyProgram $studyProgram, bool $prefill): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $courses = $spreadsheet->getActiveSheet()->setTitle('Mata Kuliah');

        $this->fillCourses($courses, $prefill ? $this->courseRows($studyProgram) : []);
        $this->addValidations($courses);

        return $spreadsheet;
    }

    public static function displayName(Lecturer $lecturer): string
    {
        return $lecturer->title ? "{$lecturer->name}, {$lecturer->title}" : $lecturer->name;
    }

    /**
     * @param  list<list<int|string|null>>  $rows
     */
    private function fillCourses(Worksheet $sheet, array $rows): void
    {
        $sheet->fromArray([self::HEADERS, ...$rows]);
        $this->styleHeader($sheet, 'A1:H1');
        $sheet->getStyle('B2:B'.self::LAST_ROW)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->freezePane('A2');

        foreach (['A' => 6, 'B' => 14, 'C' => 40, 'D' => 7, 'E' => 10, 'F' => 11, 'G' => 7, 'H' => 45] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function addValidations(Worksheet $sheet): void
    {
        $range = fn (string $column) => "{$column}2:{$column}".self::LAST_ROW;

        $sheet->setDataValidation($range('D'), $this->list('"2,3,4"', __('SKS must be 2, 3 or 4.')));
        $sheet->setDataValidation($range('E'), $this->list('"'.implode(',', range(1, 8)).'"', __('Semester must be 1 to 8.')));
        $sheet->setDataValidation($range('G'), $this->list('"'.implode(',', range('A', 'X')).'"', __('Class must be one letter A–W, or X for an enrichment class.')));

        $capacity = (new DataValidation)
            ->setType(DataValidation::TYPE_WHOLE)
            ->setOperator(DataValidation::OPERATOR_BETWEEN)
            ->setFormula1('1')
            ->setFormula2('1000')
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setShowErrorMessage(true)
            ->setError(__('Capacity must be a whole number from 1 to 1000.'));
        $sheet->setDataValidation($range('F'), $capacity);
    }

    private function list(string $formula, string $error): DataValidation
    {
        return (new DataValidation)
            ->setType(DataValidation::TYPE_LIST)
            ->setFormula1($formula)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setShowErrorMessage(true)
            ->setError($error);
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F3A93');
    }

    /**
     * @return list<list<int|string|null>>
     */
    private function courseRows(StudyProgram $studyProgram): array
    {
        $rows = [];
        $courses = Course::query()
            ->with('courseLecturers.lecturer')
            ->where('study_program_id', $studyProgram->id)
            ->orderBy('semester')
            ->orderBy('code')
            ->get();

        foreach ($courses as $course) {
            $lecturers = $course->courseLecturers->keyBy('class_number');
            for ($number = 1; $number <= $course->parallel_class_count; $number++) {
                $lecturer = $lecturers->get($number)?->lecturer;
                $rows[] = [
                    count($rows) + 1,
                    $course->code,
                    $course->name,
                    $course->sks,
                    $course->semester,
                    $course->class_capacity,
                    CourseLecturer::labelFor($number),
                    $lecturer ? self::displayName($lecturer) : null,
                ];
            }
        }

        return $rows;
    }
}
