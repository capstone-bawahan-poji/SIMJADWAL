<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\MasterData\Course\CourseImportTemplateRequest;
use App\Http\Requests\MasterData\Course\ImportCourseRequest;
use App\Models\MasterData\Course;
use App\Models\MasterData\StudyProgram;
use App\Services\MasterData\CourseImport\CourseImportTemplate;
use App\Services\MasterData\CourseImportService;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseImportController extends ApiController
{
    public function __construct(
        private readonly CourseImportService $importService,
        private readonly CourseImportTemplate $template,
    ) {}

    public function template(CourseImportTemplateRequest $request): StreamedResponse
    {
        $studyProgram = StudyProgram::query()->findOrFail($request->integer('study_program_id'));
        $this->authorize('create', [Course::class, $studyProgram]);

        $spreadsheet = $this->template->build($studyProgram, $request->boolean('prefill'));

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            "template-mata-kuliah-{$studyProgram->code}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function store(ImportCourseRequest $request): JsonResponse
    {
        $studyProgram = StudyProgram::query()->findOrFail($request->integer('study_program_id'));
        $this->authorize('create', [Course::class, $studyProgram]);

        $path = $request->file('file')->getRealPath();

        if ($request->boolean('dry_run')) {
            return $this->response($this->importService->preview($studyProgram, $path, $request->resolutions()));
        }

        $outcome = $this->importService->commit($studyProgram, $path, $request->resolutions(), (string) $request->input('plan_hash'));

        return $outcome['committed']
            ? $this->response($outcome['result'], 201)
            : $this->response($outcome['preview'], 422);
    }
}