<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BenefitController as AdminBenefitController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\MembershipController as AdminMembershipController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PublicBenefitController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('beneficios', [PublicBenefitController::class, 'index'])->name('benefits.index');
Route::get('beneficios/{benefit:slug}', [PublicBenefitController::class, 'show'])->name('benefits.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('mi-jakawi', [MembershipController::class, 'show'])->name('mi-jakawi');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminController::class)->name('index');
    Route::resource('merchants', MerchantController::class)->except(['show', 'destroy']);
    Route::resource('benefits', AdminBenefitController::class)->except(['show', 'destroy']);
    Route::get('memberships', [AdminMembershipController::class, 'index'])->name('memberships.index');
    Route::post('memberships', [AdminMembershipController::class, 'store'])->name('memberships.store');
    Route::patch('memberships/{membership}/cancel', [AdminMembershipController::class, 'cancel'])->name('memberships.cancel');
});

require __DIR__.'/settings.php';
