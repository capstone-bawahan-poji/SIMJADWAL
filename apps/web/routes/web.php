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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
