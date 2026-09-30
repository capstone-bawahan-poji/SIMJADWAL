<?php

use App\Http\Controllers\Api\Internal\Account\UserController as InternalUserController;
use App\Http\Controllers\Api\Internal\MasterData\CourseController as InternalCourseController;
use App\Http\Controllers\Api\Internal\MasterData\FacultyController as InternalFacultyController;
use App\Http\Controllers\Api\Internal\MasterData\LecturerController as InternalLecturerController;
use App\Http\Controllers\Api\Internal\MasterData\RoomController as InternalRoomController;
use App\Http\Controllers\Api\Internal\MasterData\StudyProgramController as InternalStudyProgramController;
use App\Http\Controllers\Api\Internal\MasterData\TeachingAssignmentController as InternalTeachingAssignmentController;
use App\Http\Controllers\Api\Internal\MasterData\TimeSlotController as InternalTimeSlotController;
use App\Http\Controllers\Api\Internal\MasterData\TpbGroupController as InternalTpbGroupController;
use App\Http\Controllers\Api\V1\Account\AuthController as V1AuthController;
use App\Http\Controllers\Api\V1\Account\UserController as V1UserController;
use App\Http\Controllers\Api\V1\MasterData\CourseController as V1CourseController;
use App\Http\Controllers\Api\V1\MasterData\FacultyController as V1FacultyController;
use App\Http\Controllers\Api\V1\MasterData\LecturerController as V1LecturerController;
use App\Http\Controllers\Api\V1\MasterData\RoomController as V1RoomController;
use App\Http\Controllers\Api\V1\MasterData\StudyProgramController as V1StudyProgramController;
use App\Http\Controllers\Api\V1\MasterData\TeachingAssignmentController as V1TeachingAssignmentController;
use App\Http\Controllers\Api\V1\MasterData\TimeSlotController as V1TimeSlotController;
use App\Http\Controllers\Api\V1\MasterData\TpbGroupController as V1TpbGroupController;
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

    //masterdata
    Route::apiResource('faculties', InternalFacultyController::class);
    Route::apiResource('study-programs', InternalStudyProgramController::class)->parameters(['study-programs' => 'studyProgram']);
    Route::get('study-programs/{studyProgram}/tpb-blocked-slots', [InternalStudyProgramController::class, 'tpbBlockedSlots'])->name('study-programs.tpb-blocked-slots');
    Route::apiResource('time-slots', InternalTimeSlotController::class)->parameters(['time-slots' => 'timeSlot']);
    Route::apiResource('rooms', InternalRoomController::class);
    Route::apiResource('lecturers', InternalLecturerController::class);
    Route::apiResource('courses', InternalCourseController::class);
    Route::apiResource('teaching-assignments', InternalTeachingAssignmentController::class)->parameters(['teaching-assignments' => 'courseLecturer']);
    Route::apiResource('tpb-groups', InternalTpbGroupController::class)->parameters(['tpb-groups' => 'tpbGroup']);
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

        //masterrdsata
        Route::apiResource('faculties', V1FacultyController::class);
        Route::apiResource('study-programs', V1StudyProgramController::class)->parameters(['study-programs' => 'studyProgram']);
        Route::get('study-programs/{studyProgram}/tpb-blocked-slots', [V1StudyProgramController::class, 'tpbBlockedSlots'])->name('study-programs.tpb-blocked-slots');
        Route::apiResource('time-slots', V1TimeSlotController::class)->parameters(['time-slots' => 'timeSlot']);
        Route::apiResource('rooms', V1RoomController::class);
        Route::apiResource('lecturers', V1LecturerController::class);
        Route::apiResource('courses', V1CourseController::class);
        Route::apiResource('teaching-assignments', V1TeachingAssignmentController::class)->parameters(['teaching-assignments' => 'courseLecturer']);
        Route::apiResource('tpb-groups', V1TpbGroupController::class)->parameters(['tpb-groups' => 'tpbGroup']);
    });
});
