<?php

use App\Http\Controllers\Api\Internal\Account\ActivityLogController as InternalActivityLogController;
use App\Http\Controllers\Api\Internal\Account\UserController as InternalUserController;
use App\Http\Controllers\Api\Internal\Constraint\ConstraintSubmissionController as InternalConstraintSubmissionController;
use App\Http\Controllers\Api\Internal\Constraint\ConstraintTypeController as InternalConstraintTypeController;
use App\Http\Controllers\Api\Internal\Constraint\LecturerPreferenceController as InternalLecturerPreferenceController;
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
use App\Http\Controllers\Api\Internal\Scheduling\GaPresetController as InternalGaPresetController;
use App\Http\Controllers\Api\Internal\Scheduling\SchedulingRunController as InternalSchedulingRunController;
use App\Http\Controllers\Api\V1\Account\ActivityLogController as V1ActivityLogController;
use App\Http\Controllers\Api\V1\Account\AuthController as V1AuthController;
use App\Http\Controllers\Api\V1\Account\UserController as V1UserController;
use App\Http\Controllers\Api\V1\Constraint\ConstraintSubmissionController as V1ConstraintSubmissionController;
use App\Http\Controllers\Api\V1\Constraint\ConstraintTypeController as V1ConstraintTypeController;
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
use App\Http\Controllers\Api\V1\Scheduling\GaPresetController as V1GaPresetController;
use App\Http\Controllers\Api\V1\Scheduling\SchedulingDatasetController as V1SchedulingDatasetController;
use App\Http\Controllers\Api\V1\Scheduling\SchedulingRunController as V1SchedulingRunController;
use Illuminate\Support\Facades\Route;

/*
| /api/internal/* Session + CSRF for the web (Inertia) frontend. Api\Internal controllers.
| /api/v1/*       Bearer token (Sanctum) for the mobile and desktop clients. Api\V1 controllers,
|                 versioned apart from Internal so the external contract stays stable.
| Both call the same services.
*/

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
    Route::apiResource('rooms', InternalRoomController::class);
    Route::apiResource('lecturers', InternalLecturerController::class);
    Route::get('courses/import/template', [InternalCourseImportController::class, 'template'])->name('courses.import.template');
    Route::post('courses/import', [InternalCourseImportController::class, 'store'])->name('courses.import');
    Route::apiResource('courses', InternalCourseController::class);
    Route::apiResource('teaching-assignments', InternalTeachingAssignmentController::class)->parameters(['teaching-assignments' => 'courseLecturer']);
    Route::apiResource('tpb-groups', InternalTpbGroupController::class)->parameters(['tpb-groups' => 'tpbGroup']);

    // constraints
    Route::get('constraint-types', [InternalConstraintTypeController::class, 'index'])->name('constraint-types.index');
    Route::patch('constraint-types/{constraintType}', [InternalConstraintTypeController::class, 'update'])->name('constraint-types.update');
    Route::get('lecturers/{lecturer}/preferences', [InternalLecturerPreferenceController::class, 'show'])->name('lecturers.preferences.show');
    Route::put('lecturers/{lecturer}/preferences', [InternalLecturerPreferenceController::class, 'update'])->name('lecturers.preferences.update');
    Route::get('constraint-submissions', [InternalConstraintSubmissionController::class, 'index'])->name('constraint-submissions.index');
    Route::get('constraint-submissions/{studyProgram}', [InternalConstraintSubmissionController::class, 'show'])->name('constraint-submissions.show');
    Route::post('constraint-submissions/{studyProgram}/submit', [InternalConstraintSubmissionController::class, 'submit'])->name('constraint-submissions.submit');
    Route::post('constraint-submissions/{studyProgram}/accept', [InternalConstraintSubmissionController::class, 'accept'])->name('constraint-submissions.accept');
    Route::post('constraint-submissions/{studyProgram}/return', [InternalConstraintSubmissionController::class, 'return'])->name('constraint-submissions.return');

    // scheduling
    Route::apiResource('ga-presets', InternalGaPresetController::class)->parameters(['ga-presets' => 'gaPreset']);
    Route::get('scheduling-runs', [InternalSchedulingRunController::class, 'index'])->name('scheduling-runs.index');
    Route::get('scheduling-runs/{schedulingRun}', [InternalSchedulingRunController::class, 'show'])->name('scheduling-runs.show');
    Route::get('scheduling-runs/{schedulingRun}/schedule-details', [InternalSchedulingRunController::class, 'scheduleDetails'])->name('scheduling-runs.schedule-details');
    Route::get('scheduling-runs/{schedulingRun}/violations', [InternalSchedulingRunController::class, 'violations'])->name('scheduling-runs.violations');
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

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::apiResource('users', V1UserController::class)->except(['destroy']);
        Route::patch('users/{user}/status', [V1UserController::class, 'updateStatus'])->name('users.status');
        Route::get('activity-logs', [V1ActivityLogController::class, 'index'])->name('activity-logs.index');

        // masterdata
        Route::apiResource('faculties', V1FacultyController::class);
        Route::apiResource('departments', V1DepartmentController::class);
        Route::apiResource('study-programs', V1StudyProgramController::class)->parameters(['study-programs' => 'studyProgram']);
        Route::get('study-programs/{studyProgram}/tpb-blocked-slots', [V1StudyProgramController::class, 'tpbBlockedSlots'])->name('study-programs.tpb-blocked-slots');
        Route::apiResource('time-slots', V1TimeSlotController::class)->parameters(['time-slots' => 'timeSlot']);
        Route::apiResource('rooms', V1RoomController::class);
        Route::apiResource('lecturers', V1LecturerController::class);
        // Before the resource so 'import' is not taken as a {course} id.
        Route::get('courses/import/template', [V1CourseImportController::class, 'template'])->name('courses.import.template');
        Route::post('courses/import', [V1CourseImportController::class, 'store'])->name('courses.import');
        Route::apiResource('courses', V1CourseController::class);
        Route::apiResource('teaching-assignments', V1TeachingAssignmentController::class)->parameters(['teaching-assignments' => 'courseLecturer']);
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
    });
});
