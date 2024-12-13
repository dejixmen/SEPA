<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SepaMandateController;
use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication Routes
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Protected SEPA Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/sepa', [SepaMandateController::class, 'index'])->name('sepa.index');
    Route::get('/sepa/template', [SepaMandateController::class, 'downloadTemplate'])->name('sepa.template');
    Route::post('/sepa/import', [SepaMandateController::class, 'import'])->name('sepa.import');
    Route::post('/sepa/{mandate}/charge', [SepaMandateController::class, 'charge'])->name('sepa.charge');
    Route::post('/sepa/charge-all', [SepaMandateController::class, 'chargeAll'])->name('sepa.charge-all');
    Route::get('/sepa/{mandate}/edit', [SepaMandateController::class, 'edit'])->name('sepa.edit');
    Route::put('/sepa/{mandate}', [SepaMandateController::class, 'update'])->name('sepa.update');
    Route::delete('/sepa/{mandate}', [SepaMandateController::class, 'destroy'])->name('sepa.destroy');
});

// Temporary admin creation route - DELETE THIS AFTER CREATING ADMIN
Route::get('/setup-admin/{secret_token}', function ($secret_token) {
    if ($secret_token !== 'your_temporary_secret_here') {
        abort(404);
    }

    $admin = \App\Models\User::create([
        'name' => 'Admin',
        'email' => 'your-email@example.com',
        'password' => \Illuminate\Support\Facades\Hash::make('your-secure-password'),
    ]);

    return "Admin user created successfully! Email: {$admin->email}. Please delete this route immediately!";
});
