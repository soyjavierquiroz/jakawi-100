<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MembershipController;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('mi-jakawi', [MembershipController::class, 'show'])->name('mi-jakawi');
});

require __DIR__.'/settings.php';
