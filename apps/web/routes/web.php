<?php

use App\Http\Controllers\Web\Account\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Pages (Inertia) and session flows only. Table data and CRUD go through
| /api/internal/* in routes/api.php.
*/

Route::get('/', fn () => inertia('Public/Index'))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // Cross-faculty schedule. Admins of one faculty or program have their own pages.
    Route::get('/schedule', fn () => inertia('Prodi/ScheduleMatrix/Index'))
        ->middleware('role:superadmin|admin_tpb|dosen')
        ->name('schedule.index');

    Route::middleware('role:superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/faculty-program', function () {
            return inertia('Superadmin/MasterData/FacultyProgram/Index');
        })->name('faculty-program.index');

        Route::get('/accounts', function () {
            return inertia('Superadmin/Account/Index');
        })->name('accounts.index');

        Route::get('/rooms', function () {
            return inertia('Superadmin/Room/Index');
        })->name('rooms.index');

        Route::get('/slots', function () {
            return inertia('Superadmin/Slot/Index');
        })->name('slots.index');
    });

    Route::middleware('role:admin_prodi')->prefix('prodi')->name('prodi.')->group(function () {
        Route::get('/courses', fn () => inertia('Prodi/Course/Index'))->name('courses.index');
        Route::get('/courses/import', fn () => inertia('Prodi/Course/Import'))->name('courses.import');
        Route::get('/lecturers', fn () => inertia('Prodi/Lecturer/Index'))->name('lecturers.index');
        Route::get('/mappings', fn () => inertia('Prodi/Mapping/Index'))->name('mappings.index');
        Route::get('/constraints', fn () => inertia('Prodi/Constraint/Index'))->name('constraints.index');
        Route::get('/constraints/review', fn () => inertia('Prodi/Constraint/Review'))->name('constraints.review');
        Route::get('/schedule-matrix', fn () => inertia('Prodi/ScheduleMatrix/Index'))->name('schedule-matrix.index');
        Route::get('/ga', fn () => inertia('Fakultas/Ga/Index', ['scope' => 'prodi']))->name('ga.index');
        Route::get('/rooms', fn () => inertia('Fakultas/Room/Index', ['scope' => 'prodi']))->name('rooms.index');
    });

    Route::middleware('role:admin_fakultas')->prefix('fakultas')->name('fakultas.')->group(function () {
        Route::get('/schedule', fn () => inertia('Fakultas/Schedule/Index'))->name('schedule.index');
        Route::get('/tpb-import', fn () => inertia('Fakultas/TpbImport/Index'))->name('tpb-import.index');
        Route::get('/program-slot-allocation', fn () => inertia('Fakultas/ProgramSlotAllocation/Index'))->name('program-slot-allocation.index');
        Route::get('/rooms', fn () => inertia('Fakultas/Room/Index'))->name('rooms.index');
    });

    Route::middleware('role:admin_tpb')->prefix('tpb')->name('tpb.')->group(function () {
        Route::get('/schedule', fn () => inertia('Tpb/Schedule/Index'))->name('schedule.index');
        Route::get('/schedule-import', fn () => inertia('Tpb/ScheduleImport/Index'))->name('schedule-import.index');
        Route::get('/courses', fn () => inertia('Tpb/Course/Index'))->name('courses.index');
        Route::get('/lecturers', fn () => inertia('Tpb/Lecturer/Index'))->name('lecturers.index');
        Route::get('/mappings', fn () => inertia('Tpb/Mapping/Index'))->name('mappings.index');
        Route::get('/mappings/import', fn () => inertia('Tpb/Mapping/Import'))->name('mappings.import');
        Route::get('/ga', fn () => inertia('Tpb/Ga/Index'))->name('ga.index');
        Route::get('/rooms', fn () => inertia('Tpb/Room/Index'))->name('rooms.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
