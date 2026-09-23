<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PartnerPortalController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RedemptionController;
use App\Http\Controllers\ExperienceReservationController;
use App\Http\Controllers\PartnerReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/partners/{partner:slug}', [PublicController::class, 'partners'])->name('partners.show');
Route::get('/partners/{partner:slug}/whatsapp', [PublicController::class, 'partnerWhatsapp'])->name('partners.whatsapp');
Route::get('/lugares/{location:slug}', [PublicController::class, 'location'])->name('locations.show');
Route::get('/lugares/{location:slug}/mapa', [PublicController::class, 'map'])->name('locations.map');
Route::get('/lugares/{location:slug}/whatsapp', [PublicController::class, 'locationWhatsapp'])->name('locations.whatsapp');
Route::get('/beneficios', [PublicController::class, 'benefits'])->name('benefits.index');
Route::get('/beneficios/{benefit:slug}', [PublicController::class, 'benefit'])->name('benefits.show');
Route::get('/experiencias', [PublicController::class, 'experiences'])->name('experiences.index');
Route::get('/experiencias/{experience:slug}', [PublicController::class, 'experience'])->name('experiences.show');
Route::get('/experiencias/{experience:slug}/reservar', [PublicController::class, 'reserve'])->name('experiences.reserve');
Route::middleware(['auth', 'verified', 'partner'])->group(function () {
    Route::get('/partner', [PartnerPortalController::class, 'index'])->name('partner.index');
    Route::get('/partner/reservas', [PartnerReservationController::class, 'index'])->name('partner.reservations.index');
    Route::post('/partner/reservas/{reservation:public_id}/{status}', [PartnerReservationController::class, 'respond'])->whereIn('status', ['confirmed', 'rejected'])->name('partner.reservations.respond');
    Route::get('/validar', [RedemptionController::class, 'form'])->name('redemptions.validate');
    Route::post('/validar', [RedemptionController::class, 'confirm'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => to_route('home'))->name('dashboard');
    Route::get('mi-jakawi', [MembershipController::class, 'show'])->name('mi-jakawi');
    Route::post('/experiencias/{experience:slug}/reservas', [ExperienceReservationController::class, 'store'])->name('experiences.reservations.store');
    Route::post('/reservas/{reservation:public_id}/cancelar', [ExperienceReservationController::class, 'cancel'])->name('experiences.reservations.cancel');
    Route::post('/beneficios/{benefit:slug}/canjear', [RedemptionController::class, 'start'])->name('redemptions.start');
    Route::get('/canjes/{redemption:public_id}', [RedemptionController::class, 'show'])->name('redemptions.show');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.index');
    Route::get('partners', [AdminController::class, 'partners'])->name('admin.partners.index');
    Route::get('partners/create', [AdminController::class, 'partnerForm'])->name('admin.partners.create');
    Route::post('partners', [AdminController::class, 'savePartner'])->name('admin.partners.store');
    Route::get('partners/{partner}/edit', [AdminController::class, 'partnerForm'])->name('admin.partners.edit');
    Route::put('partners/{partner}', [AdminController::class, 'savePartner'])->name('admin.partners.update');
    Route::get('locations', [AdminController::class, 'locations'])->name('admin.locations.index');
    Route::get('locations/create', [AdminController::class, 'locationForm'])->name('admin.locations.create');
    Route::post('locations', [AdminController::class, 'saveLocation'])->name('admin.locations.store');
    Route::get('locations/{location}/edit', [AdminController::class, 'locationForm'])->name('admin.locations.edit');
    Route::put('locations/{location}', [AdminController::class, 'saveLocation'])->name('admin.locations.update');
    Route::get('benefits', [AdminController::class, 'benefits'])->name('admin.benefits.index');
    Route::get('benefits/create', [AdminController::class, 'benefitForm'])->name('admin.benefits.create');
    Route::post('benefits', [AdminController::class, 'saveBenefit'])->name('admin.benefits.store');
    Route::get('benefits/{benefit}/edit', [AdminController::class, 'benefitForm'])->name('admin.benefits.edit');
    Route::put('benefits/{benefit}', [AdminController::class, 'saveBenefit'])->name('admin.benefits.update');
    Route::get('experiences', [AdminController::class, 'experiences'])->name('admin.experiences.index');
    Route::get('experiences/create', [AdminController::class, 'experienceForm'])->name('admin.experiences.create');
    Route::post('experiences', [AdminController::class, 'saveExperience'])->name('admin.experiences.store');
    Route::get('experiences/{experience}/edit', [AdminController::class, 'experienceForm'])->name('admin.experiences.edit');
    Route::put('experiences/{experience}', [AdminController::class, 'saveExperience'])->name('admin.experiences.update');
    Route::post('experiences/{experience}/sessions', [AdminController::class, 'sessions'])->name('admin.experiences.sessions.store');
    Route::put('experiences/{experience}/sessions/{session}', [AdminController::class, 'updateSession'])->name('admin.experiences.sessions.update');
    Route::delete('experiences/{experience}/sessions/{session}', [AdminController::class, 'deleteSession'])->name('admin.experiences.sessions.destroy');
    Route::get('memberships', [AdminController::class, 'memberships'])->name('admin.memberships.index');
    Route::post('memberships', [AdminController::class, 'activateMembership'])->name('admin.memberships.store');
    Route::delete('memberships/{membership}', [AdminController::class, 'cancelMembership'])->name('admin.memberships.cancel');
    Route::get('redemptions', [AdminController::class, 'redemptions'])->name('admin.redemptions.index');
});

require __DIR__.'/settings.php';
