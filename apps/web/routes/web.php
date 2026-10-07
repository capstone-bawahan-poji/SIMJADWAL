<?php

use App\Http\Controllers\Web\Account\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Pages (Inertia) and session flows only. Table data and CRUD go through
| /api/internal/* in routes/api.php.
*/

Route::redirect('/', '/login');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

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
        Route::get('/lecturers', fn () => inertia('Prodi/Lecturer/Index'))->name('lecturers.index');
        Route::get('/mappings', fn () => inertia('Prodi/Mapping/Index'))->name('mappings.index');
        Route::get('/constraints', fn () => inertia('Prodi/Constraint/Index'))->name('constraints.index');
        Route::get('/constraints/review', fn () => inertia('Prodi/Constraint/Review'))->name('constraints.review');
        Route::get('/schedule-matrix', fn () => inertia('Prodi/ScheduleMatrix/Index'))->name('schedule-matrix.index');
    });

    Route::middleware('role:admin_fakultas')->prefix('fakultas')->name('fakultas.')->group(function () {
        Route::get('/constraints', fn () => inertia('Fakultas/Constraint/Index'))->name('constraints.index');
        Route::get('/ga', fn () => inertia('Fakultas/Ga/Index'))->name('ga.index');
        Route::get('/rooms', fn () => inertia('Fakultas/Room/Index'))->name('rooms.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
