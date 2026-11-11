<?php

use App\Http\Controllers\Api\Catalog\CatalogController;
use App\Http\Controllers\Api\Internal\Account\ActivityLogController as InternalActivityLogController;
use App\Http\Controllers\Api\Internal\Account\UserController as InternalUserController;
use App\Http\Controllers\Api\Internal\Constraint\ConstraintSubmissionController as InternalConstraintSubmissionController;
use App\Http\Controllers\Api\Internal\Constraint\ConstraintTypeController as InternalConstraintTypeController;
use App\Http\Controllers\Api\Internal\Constraint\LecturerPreferenceController as InternalLecturerPreferenceController;
use App\Http\Controllers\Api\Internal\MasterData\AcademicPeriodController as InternalAcademicPeriodController;
use App\Http\Controllers\Api\Internal\MasterData\CourseController as InternalCourseController;
use App\Http\Controllers\Api\Internal\MasterData\CourseImportController as InternalCourseImportController;
use App\Http\Controllers\Api\Internal\MasterData\DepartmentController as InternalDepartmentController;
use App\Http\Controllers\Api\Internal\MasterData\FacultyController as InternalFacultyController;
use App\Http\Controllers\Api\Internal\MasterData\LecturerController as InternalLecturerController;
use App\Http\Controllers\Api\Internal\MasterData\RoomController as InternalRoomController;
use App\Http\Controllers\Api\Internal\MasterData\StudyProgramController as InternalStudyProgramController;
use App\Http\Controllers\Api\Internal\MasterData\TeachingAssignmentController as InternalTeachingAssignmentController;
use App\Http\Controllers\Api\Internal\MasterData\TimeSlotController as InternalTimeSlotController;
use App\Http\Controllers\Api\Internal\MasterData\TpbGroupController as InternalTpbGroupController;
use App\Http\Controllers\Api\Internal\MasterData\TpbImportController as InternalTpbImportController;
use App\Http\Controllers\Api\Internal\MasterData\TpbOfferingController as InternalTpbOfferingController;
use App\Http\Controllers\Api\Internal\Scheduling\GaPresetController as InternalGaPresetController;
use App\Http\Controllers\Api\Internal\Scheduling\ProgramScheduleController as InternalProgramScheduleController;
use App\Http\Controllers\Api\Internal\Scheduling\RoomSlotAllocationController as InternalRoomSlotAllocationController;
use App\Http\Controllers\Api\Internal\Scheduling\ScheduleController as InternalScheduleController;
use App\Http\Controllers\Api\Internal\Scheduling\SchedulePublicationController as InternalSchedulePublicationController;
use App\Http\Controllers\Api\Internal\Scheduling\SchedulingRunController as InternalSchedulingRunController;
use App\Http\Controllers\Api\Internal\Scheduling\SlotRequestController as InternalSlotRequestController;
use App\Http\Controllers\Api\Internal\Scheduling\TpbScheduleImportController as InternalTpbScheduleImportController;
use App\Http\Controllers\Api\V1\Account\ActivityLogController as V1ActivityLogController;
use App\Http\Controllers\Api\V1\Account\AuthController as V1AuthController;
use App\Http\Controllers\Api\V1\Account\UserController as V1UserController;
use App\Http\Controllers\Api\V1\Constraint\ConstraintSubmissionController as V1ConstraintSubmissionController;
use App\Http\Controllers\Api\V1\Constraint\ConstraintTypeController as V1ConstraintTypeController;
use App\Http\Controllers\Api\V1\MasterData\AcademicPeriodController as V1AcademicPeriodController;
use App\Http\Controllers\Api\V1\MasterData\CourseController as V1CourseController;
use App\Http\Controllers\Api\V1\MasterData\CourseImportController as V1CourseImportController;
use App\Http\Controllers\Api\V1\MasterData\DepartmentController as V1DepartmentController;
use App\Http\Controllers\Api\V1\MasterData\FacultyController as V1FacultyController;
use App\Http\Controllers\Api\V1\MasterData\LecturerController as V1LecturerController;
use App\Http\Controllers\Api\V1\MasterData\RoomController as V1RoomController;
use App\Http\Controllers\Api\V1\MasterData\StudyProgramController as V1StudyProgramController;
use App\Http\Controllers\Api\V1\MasterData\TeachingAssignmentController as V1TeachingAssignmentController;
use App\Http\Controllers\Api\V1\MasterData\TimeSlotController as V1TimeSlotController;
use App\Http\Controllers\Api\V1\MasterData\TpbGroupController as V1TpbGroupController;
use App\Http\Controllers\Api\V1\MasterData\TpbImportController as V1TpbImportController;
use App\Http\Controllers\Api\V1\MasterData\TpbOfferingController as V1TpbOfferingController;
use App\Http\Controllers\Api\V1\Scheduling\GaPresetController as V1GaPresetController;
use App\Http\Controllers\Api\V1\Scheduling\ProgramScheduleController as V1ProgramScheduleController;
use App\Http\Controllers\Api\V1\Scheduling\PublishedScheduleController as V1PublishedScheduleController;
use App\Http\Controllers\Api\V1\Scheduling\RoomSlotAllocationController as V1RoomSlotAllocationController;
use App\Http\Controllers\Api\V1\Scheduling\ScheduleController as V1ScheduleController;
use App\Http\Controllers\Api\V1\Scheduling\SchedulePublicationController as V1SchedulePublicationController;
use App\Http\Controllers\Api\V1\Scheduling\SchedulingDatasetController as V1SchedulingDatasetController;
use App\Http\Controllers\Api\V1\Scheduling\SchedulingRunController as V1SchedulingRunController;
use App\Http\Controllers\Api\V1\Scheduling\SlotRequestController as V1SlotRequestController;
use App\Http\Controllers\Api\V1\Scheduling\TpbScheduleImportController as V1TpbScheduleImportController;
use App\Http\Controllers\Api\V1\Shared\BroadcastAuthController as V1BroadcastAuthController;
use Illuminate\Support\Facades\Route;

/*
| /api/internal/* Session + CSRF for the web (Inertia) frontend. Api\Internal controllers.
| /api/v1/*       Bearer token (Sanctum) for the mobile and desktop clients. Api\V1 controllers,
|                 versioned apart from Internal so the external contract stays stable.
| /api/public/*   No auth. Read-only catalog and the email request of the landing page. Throttled.
| Internal and v1 call the same services.
*/

Route::middleware('throttle:public')->prefix('public')->name('api.public.')->group(function () {
    Route::get('faculties', [CatalogController::class, 'faculties'])->name('faculties.index');
    Route::get('courses', [CatalogController::class, 'courses'])->name('courses.index');
    Route::post('schedule-email', [CatalogController::class, 'sendSchedule'])->middleware('throttle:schedule-email')->name('schedule-email');
});

Route::middleware(['web', 'auth'])->prefix('internal')->name('api.internal.')->group(function () {
    Route::apiResource('users', InternalUserController::class)->except(['destroy']);
    Route::patch('users/{user}/status', [InternalUserController::class, 'updateStatus'])->name('users.status');
    Route::get('activity-logs', [InternalActivityLogController::class, 'index'])->name('activity-logs.index');

    // masterdata
    Route::apiResource('faculties', InternalFacultyController::class);
    Route::apiResource('departments', InternalDepartmentController::class);
    Route::apiResource('study-programs', InternalStudyProgramController::class)->parameters(['study-programs' => 'studyProgram']);
    Route::get('study-programs/{studyProgram}/tpb-blocked-slots', [InternalStudyProgramController::class, 'tpbBlockedSlots'])->name('study-programs.tpb-blocked-slots');
    Route::apiResource('time-slots', InternalTimeSlotController::class)->parameters(['time-slots' => 'timeSlot']);
    Route::apiResource('academic-periods', InternalAcademicPeriodController::class)->parameters(['academic-periods' => 'academicPeriod']);
    Route::apiResource('rooms', InternalRoomController::class);
    Route::apiResource('lecturers', InternalLecturerController::class);
    Route::get('courses/import/template', [InternalCourseImportController::class, 'template'])->name('courses.import.template');
    Route::post('courses/import', [InternalCourseImportController::class, 'store'])->name('courses.import');
    Route::apiResource('courses', InternalCourseController::class);
    Route::apiResource('teaching-assignments', InternalTeachingAssignmentController::class)->parameters(['teaching-assignments' => 'courseLecturer']);
    Route::get('tpb-offerings/import/template', [InternalTpbImportController::class, 'template'])->name('tpb-offerings.import.template');
    Route::post('tpb-offerings/import', [InternalTpbImportController::class, 'store'])->name('tpb-offerings.import');
    Route::apiResource('tpb-offerings', InternalTpbOfferingController::class)->parameters(['tpb-offerings' => 'tpbOffering']);
    Route::apiResource('tpb-groups', InternalTpbGroupController::class)->parameters(['tpb-groups' => 'tpbGroup']);

    // constraints
    Route::get('constraint-types', [InternalConstraintTypeController::class, 'index'])->name('constraint-types.index');
    Route::patch('constraint-types/{constraintType}', [InternalConstraintTypeController::class, 'update'])->name('constraint-types.update');
    Route::get('lecturers/{lecturer}/preferences', [InternalLecturerPreferenceController::class, 'show'])->name('lecturers.preferences.show');
    Route::put('lecturers/{lecturer}/preferences', [InternalLecturerPreferenceController::class, 'update'])->name('lecturers.preferences.update');
    Route::get('constraint-submissions', [InternalConstraintSubmissionController::class, 'index'])->name('constraint-submissions.index');
    Route::get('constraint-submissions/{studyProgram}', [InternalConstraintSubmissionController::class, 'show'])->name('constraint-submissions.show');

    // scheduling
    Route::apiResource('ga-presets', InternalGaPresetController::class)->parameters(['ga-presets' => 'gaPreset']);
    Route::get('scheduling-runs', [InternalSchedulingRunController::class, 'index'])->name('scheduling-runs.index');
    Route::get('scheduling-runs/{schedulingRun}', [InternalSchedulingRunController::class, 'show'])->name('scheduling-runs.show');
    Route::get('scheduling-runs/{schedulingRun}/schedule-details', [InternalSchedulingRunController::class, 'scheduleDetails'])->name('scheduling-runs.schedule-details');
    Route::get('scheduling-runs/{schedulingRun}/violations', [InternalSchedulingRunController::class, 'violations'])->name('scheduling-runs.violations');
    Route::post('scheduling-runs/{schedulingRun}/activate', [InternalSchedulingRunController::class, 'activate'])->name('scheduling-runs.activate');
    Route::post('scheduling-runs/{schedulingRun}/publish', [InternalSchedulingRunController::class, 'publish'])->name('scheduling-runs.publish');
    Route::get('schedules', [InternalScheduleController::class, 'index'])->name('schedules.index');
    Route::get('schedules/export', [InternalScheduleController::class, 'export'])->name('schedules.export');
    Route::get('schedules/room-grid', [InternalScheduleController::class, 'roomGrid'])->name('schedules.room-grid');
    Route::get('schedules/export-room-grid', [InternalScheduleController::class, 'exportRoomGrid'])->name('schedules.export-room-grid');
    Route::get('schedule-details/{scheduleDetail}/options', [InternalScheduleController::class, 'options'])->name('schedule-details.options');
    Route::post('schedule-details/swap', [InternalScheduleController::class, 'swap'])->name('schedule-details.swap');
    Route::patch('schedule-details/{scheduleDetail}', [InternalScheduleController::class, 'override'])->name('schedule-details.override');
    Route::get('tpb-blocks/{scheduleDetail}/options', [InternalScheduleController::class, 'tpbOptions'])->name('tpb-blocks.options');
    Route::post('tpb-blocks/swap', [InternalScheduleController::class, 'tpbSwap'])->name('tpb-blocks.swap');
    Route::patch('tpb-blocks/{scheduleDetail}', [InternalScheduleController::class, 'tpbOverride'])->name('tpb-blocks.override');

    // POS 040/IT10.1/OT.07/2026: TPB data, room slots per program, slot requests, review and publication
    Route::get('tpb-schedule/import/template', [InternalTpbScheduleImportController::class, 'template'])->name('tpb-schedule.import.template');
    Route::post('tpb-schedule/import', [InternalTpbScheduleImportController::class, 'tpbStore'])->name('tpb-schedule.import');
    Route::get('tpb-occupancy', [InternalTpbScheduleImportController::class, 'facultyShow'])->name('tpb-occupancy.show');
    Route::post('tpb-occupancy/import', [InternalTpbScheduleImportController::class, 'facultyStore'])->name('tpb-occupancy.import');
    Route::delete('tpb-occupancy', [InternalTpbScheduleImportController::class, 'facultyDestroy'])->name('tpb-occupancy.destroy');
    Route::get('room-slot-allocations', [InternalRoomSlotAllocationController::class, 'index'])->name('room-slot-allocations.index');
    Route::put('room-slot-allocations', [InternalRoomSlotAllocationController::class, 'sync'])->name('room-slot-allocations.sync');
    Route::get('study-programs/{studyProgram}/room-slots', [InternalRoomSlotAllocationController::class, 'program'])->name('study-programs.room-slots');
    Route::get('slot-requests', [InternalSlotRequestController::class, 'index'])->name('slot-requests.index');
    Route::post('slot-requests', [InternalSlotRequestController::class, 'store'])->name('slot-requests.store');
    Route::post('slot-requests/{slotRequest}/fulfill', [InternalSlotRequestController::class, 'fulfill'])->name('slot-requests.fulfill');
    Route::post('slot-requests/{slotRequest}/reject', [InternalSlotRequestController::class, 'reject'])->name('slot-requests.reject');
    Route::delete('slot-requests/{slotRequest}', [InternalSlotRequestController::class, 'destroy'])->name('slot-requests.destroy');
    Route::get('study-programs/{studyProgram}/schedule-submission', [InternalProgramScheduleController::class, 'show'])->name('study-programs.schedule-submission.show');
    Route::post('study-programs/{studyProgram}/schedule-submission/submit', [InternalProgramScheduleController::class, 'submit'])->name('study-programs.schedule-submission.submit');
    Route::post('study-programs/{studyProgram}/schedule-submission/withdraw', [InternalProgramScheduleController::class, 'withdraw'])->name('study-programs.schedule-submission.withdraw');
    Route::post('study-programs/{studyProgram}/schedule-submission/accept', [InternalProgramScheduleController::class, 'accept'])->name('study-programs.schedule-submission.accept');
    Route::post('study-programs/{studyProgram}/schedule-submission/return', [InternalProgramScheduleController::class, 'return'])->name('study-programs.schedule-submission.return');
    Route::get('faculties/{faculty}/schedule-submissions', [InternalProgramScheduleController::class, 'faculty'])->name('faculties.schedule-submissions');
    Route::get('faculties/{faculty}/schedule-publication', [InternalSchedulePublicationController::class, 'show'])->name('faculties.schedule-publication.show');
    Route::post('faculties/{faculty}/schedule-publication', [InternalSchedulePublicationController::class, 'store'])->name('faculties.schedule-publication.store');
    Route::delete('faculties/{faculty}/schedule-publication', [InternalSchedulePublicationController::class, 'destroy'])->name('faculties.schedule-publication.destroy');
});

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [V1AuthController::class, 'login'])->middleware('throttle:login')->name('login');
        Route::post('forgot-password', [V1AuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('forgot-password');
        Route::post('reset-password', [V1AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('reset-password');

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::post('logout', [V1AuthController::class, 'logout'])->name('logout');
            Route::get('me', [V1AuthController::class, 'me'])->name('me');
        });
    });

    Route::middleware(['auth:sanctum', 'active', 'no-integration-token'])->group(function () {
        Route::post('broadcasting/auth', V1BroadcastAuthController::class)->name('broadcasting.auth');

        Route::apiResource('users', V1UserController::class)->except(['destroy']);
        Route::patch('users/{user}/status', [V1UserController::class, 'updateStatus'])->name('users.status');
        Route::get('activity-logs', [V1ActivityLogController::class, 'index'])->name('activity-logs.index');

        // masterdata
        Route::apiResource('faculties', V1FacultyController::class);
        Route::apiResource('departments', V1DepartmentController::class);
        Route::apiResource('study-programs', V1StudyProgramController::class)->parameters(['study-programs' => 'studyProgram']);
        Route::get('study-programs/{studyProgram}/tpb-blocked-slots', [V1StudyProgramController::class, 'tpbBlockedSlots'])->name('study-programs.tpb-blocked-slots');
        Route::apiResource('time-slots', V1TimeSlotController::class)->parameters(['time-slots' => 'timeSlot']);
        Route::apiResource('academic-periods', V1AcademicPeriodController::class)->parameters(['academic-periods' => 'academicPeriod']);
        Route::apiResource('rooms', V1RoomController::class);
        Route::apiResource('lecturers', V1LecturerController::class);
        // Before the resource so 'import' is not taken as a {course} id.
        Route::get('courses/import/template', [V1CourseImportController::class, 'template'])->name('courses.import.template');
        Route::post('courses/import', [V1CourseImportController::class, 'store'])->name('courses.import');
        Route::apiResource('courses', V1CourseController::class);
        Route::apiResource('teaching-assignments', V1TeachingAssignmentController::class)->parameters(['teaching-assignments' => 'courseLecturer']);
        Route::get('tpb-offerings/import/template', [V1TpbImportController::class, 'template'])->name('tpb-offerings.import.template');
        Route::post('tpb-offerings/import', [V1TpbImportController::class, 'store'])->name('tpb-offerings.import');
        Route::apiResource('tpb-offerings', V1TpbOfferingController::class)->parameters(['tpb-offerings' => 'tpbOffering']);
        Route::apiResource('tpb-groups', V1TpbGroupController::class)->parameters(['tpb-groups' => 'tpbGroup']);

        // constraints (read-only for the desktop app)
        Route::get('constraint-types', [V1ConstraintTypeController::class, 'index'])->name('constraint-types.index');
        Route::get('constraint-submissions', [V1ConstraintSubmissionController::class, 'index'])->name('constraint-submissions.index');
        Route::get('constraint-submissions/{studyProgram}', [V1ConstraintSubmissionController::class, 'show'])->name('constraint-submissions.show');

        // scheduling
        Route::get('scheduling/dataset', [V1SchedulingDatasetController::class, 'show'])->name('scheduling.dataset');
        Route::apiResource('ga-presets', V1GaPresetController::class)->parameters(['ga-presets' => 'gaPreset']);
        Route::get('scheduling-runs', [V1SchedulingRunController::class, 'index'])->name('scheduling-runs.index');
        Route::post('scheduling-runs', [V1SchedulingRunController::class, 'store'])->name('scheduling-runs.store');
        Route::get('scheduling-runs/{schedulingRun}', [V1SchedulingRunController::class, 'show'])->name('scheduling-runs.show');
        Route::get('scheduling-runs/{schedulingRun}/schedule-details', [V1SchedulingRunController::class, 'scheduleDetails'])->name('scheduling-runs.schedule-details');
        Route::get('scheduling-runs/{schedulingRun}/violations', [V1SchedulingRunController::class, 'violations'])->name('scheduling-runs.violations');
        Route::post('scheduling-runs/{schedulingRun}/activate', [V1SchedulingRunController::class, 'activate'])->name('scheduling-runs.activate');
        Route::post('scheduling-runs/{schedulingRun}/publish', [V1SchedulingRunController::class, 'publish'])->name('scheduling-runs.publish');
        Route::get('schedules', [V1ScheduleController::class, 'index'])->name('schedules.index');
        Route::get('schedules/export', [V1ScheduleController::class, 'export'])->name('schedules.export');
        Route::get('schedule-details/{scheduleDetail}/options', [V1ScheduleController::class, 'options'])->name('schedule-details.options');
        Route::post('schedule-details/swap', [V1ScheduleController::class, 'swap'])->name('schedule-details.swap');
        Route::patch('schedule-details/{scheduleDetail}', [V1ScheduleController::class, 'override'])->name('schedule-details.override');
        Route::get('tpb-blocks/{scheduleDetail}/options', [V1ScheduleController::class, 'tpbOptions'])->name('tpb-blocks.options');
        Route::post('tpb-blocks/swap', [V1ScheduleController::class, 'tpbSwap'])->name('tpb-blocks.swap');
        Route::patch('tpb-blocks/{scheduleDetail}', [V1ScheduleController::class, 'tpbOverride'])->name('tpb-blocks.override');

        // POS 040/IT10.1/OT.07/2026: TPB data, room slots per program, slot requests, review and publication
        Route::get('tpb-schedule/import/template', [V1TpbScheduleImportController::class, 'template'])->name('tpb-schedule.import.template');
        Route::post('tpb-schedule/import', [V1TpbScheduleImportController::class, 'tpbStore'])->name('tpb-schedule.import');
        Route::get('tpb-occupancy', [V1TpbScheduleImportController::class, 'facultyShow'])->name('tpb-occupancy.show');
        Route::post('tpb-occupancy/import', [V1TpbScheduleImportController::class, 'facultyStore'])->name('tpb-occupancy.import');
        Route::delete('tpb-occupancy', [V1TpbScheduleImportController::class, 'facultyDestroy'])->name('tpb-occupancy.destroy');
        Route::get('room-slot-allocations', [V1RoomSlotAllocationController::class, 'index'])->name('room-slot-allocations.index');
        Route::put('room-slot-allocations', [V1RoomSlotAllocationController::class, 'sync'])->name('room-slot-allocations.sync');
        Route::get('study-programs/{studyProgram}/room-slots', [V1RoomSlotAllocationController::class, 'program'])->name('study-programs.room-slots');
        Route::get('slot-requests', [V1SlotRequestController::class, 'index'])->name('slot-requests.index');
        Route::post('slot-requests', [V1SlotRequestController::class, 'store'])->name('slot-requests.store');
        Route::post('slot-requests/{slotRequest}/fulfill', [V1SlotRequestController::class, 'fulfill'])->name('slot-requests.fulfill');
        Route::post('slot-requests/{slotRequest}/reject', [V1SlotRequestController::class, 'reject'])->name('slot-requests.reject');
        Route::delete('slot-requests/{slotRequest}', [V1SlotRequestController::class, 'destroy'])->name('slot-requests.destroy');
        Route::get('study-programs/{studyProgram}/schedule-submission', [V1ProgramScheduleController::class, 'show'])->name('study-programs.schedule-submission.show');
        Route::post('study-programs/{studyProgram}/schedule-submission/submit', [V1ProgramScheduleController::class, 'submit'])->name('study-programs.schedule-submission.submit');
        Route::post('study-programs/{studyProgram}/schedule-submission/withdraw', [V1ProgramScheduleController::class, 'withdraw'])->name('study-programs.schedule-submission.withdraw');
        Route::post('study-programs/{studyProgram}/schedule-submission/accept', [V1ProgramScheduleController::class, 'accept'])->name('study-programs.schedule-submission.accept');
        Route::post('study-programs/{studyProgram}/schedule-submission/return', [V1ProgramScheduleController::class, 'return'])->name('study-programs.schedule-submission.return');
        Route::get('faculties/{faculty}/schedule-submissions', [V1ProgramScheduleController::class, 'faculty'])->name('faculties.schedule-submissions');
        Route::get('faculties/{faculty}/schedule-publication', [V1SchedulePublicationController::class, 'show'])->name('faculties.schedule-publication.show');
        Route::post('faculties/{faculty}/schedule-publication', [V1SchedulePublicationController::class, 'store'])->name('faculties.schedule-publication.store');
        Route::delete('faculties/{faculty}/schedule-publication', [V1SchedulePublicationController::class, 'destroy'])->name('faculties.schedule-publication.destroy');
    });

    // Integration API for other campus systems: published schedule only. Integration tokens reach nothing else.
    Route::middleware(['auth:sanctum', 'active', 'throttle:120,1'])->group(function () {
        Route::get('published-schedules', [V1PublishedScheduleController::class, 'index'])->name('published-schedules.index');
        Route::get('published-schedules/publications', [V1PublishedScheduleController::class, 'publications'])->name('published-schedules.publications');
    });
});
