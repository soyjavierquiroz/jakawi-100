<?php

use App\Http\Controllers\Identity\ClubIdentityController;
use App\Http\Middleware\AuthenticateClubIdentity;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/identity')->middleware([AuthenticateClubIdentity::class, 'throttle:club-identity'])->group(function (): void {
    Route::post('handoffs', [ClubIdentityController::class, 'store'])->name('identity.handoffs.store');
    Route::get('handoffs/{handoff}', [ClubIdentityController::class, 'show'])->whereUuid('handoff')->name('identity.handoffs.show');
    Route::get('links/{clubUser}', [ClubIdentityController::class, 'link'])->whereUuid('clubUser')->name('identity.links.show');
    Route::post('links/{clubUser}/revoke', [ClubIdentityController::class, 'revoke'])->whereUuid('clubUser')->name('identity.links.revoke');
});
