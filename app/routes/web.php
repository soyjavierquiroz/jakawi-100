<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BenefitController as AdminBenefitController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicBenefitController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('beneficios', [PublicBenefitController::class, 'index'])->name('benefits.index');
Route::get('beneficios/{benefit:slug}', [PublicBenefitController::class, 'show'])->name('benefits.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminController::class)->name('index');
    Route::resource('merchants', MerchantController::class)->except(['show', 'destroy']);
    Route::resource('benefits', AdminBenefitController::class)->except(['show', 'destroy']);
});

require __DIR__.'/settings.php';
