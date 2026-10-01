<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ==========================================
// DASHBOARD
// ==========================================

// Langsung tampilkan dashboard saat membuka /
Route::get('/', function () {
    return view('dashboard');
});

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');


// ==========================================
// KELOLA CONSTRAINT
// ==========================================

Route::get('/kelola-constraint', function () {
    return view('kelola_constraint');
})->name('kelola.constraint');


// ==========================================
// PENJADWALAN GENETIC ALGORITHM
// ==========================================

Route::get('/penjadwalan-ga', function () {
    return view('penjadwalan_ga');
})->name('penjadwalan.ga');


// ==========================================
// ALOKASI RUANGAN
// ==========================================

Route::get('/alokasi-ruangan', function () {
    return view('alokasi_ruangan');
})->name('alokasi.ruangan');


// ==========================================
// RESET PASSWORD
// ==========================================

Route::get('/reset-password', function () {
    return view('auth.reset-password');
})->name('password.reset');


// ==========================================
// PROFILE
// ==========================================

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});


// ==========================================
// AUTH
// ==========================================

require __DIR__.'/auth.php';