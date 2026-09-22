<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BenefitController as AdminBenefitController;
use App\Http\Controllers\Admin\MembershipController as AdminMembershipController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\RedemptionController as AdminRedemptionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PublicBenefitController;
use App\Http\Controllers\RedemptionController;
use App\Http\Controllers\RedemptionValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('beneficios', [PublicBenefitController::class, 'index'])->name('benefits.index');
Route::get('beneficios/{benefit:slug}', [PublicBenefitController::class, 'show'])->name('benefits.show');
Route::get('validar', [RedemptionValidationController::class, 'create'])->name('redemptions.validate.create');
Route::post('validar', [RedemptionValidationController::class, 'store'])->middleware('throttle:redemption-validator')->name('redemptions.validate.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('mi-jakawi', [MembershipController::class, 'show'])->name('mi-jakawi');
    Route::post('beneficios/{benefit:slug}/canjear', [RedemptionController::class, 'store'])->name('redemptions.store');
    Route::get('canjes/{redemption}', [RedemptionController::class, 'show'])->name('redemptions.show');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminController::class)->name('index');
    Route::resource('merchants', MerchantController::class)->except(['show', 'destroy']);
    Route::resource('benefits', AdminBenefitController::class)->except(['show', 'destroy']);
    Route::get('memberships', [AdminMembershipController::class, 'index'])->name('memberships.index');
    Route::post('memberships', [AdminMembershipController::class, 'store'])->name('memberships.store');
    Route::patch('memberships/{membership}/cancel', [AdminMembershipController::class, 'cancel'])->name('memberships.cancel');
    Route::get('redemptions', [AdminRedemptionController::class, 'index'])->name('redemptions.index');
});

require __DIR__.'/settings.php';
