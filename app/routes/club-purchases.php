<?php

use App\Http\Controllers\ClubPurchaseController;
use App\Http\Middleware\AuthenticateClubPurchase;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/club/purchases')->middleware([AuthenticateClubPurchase::class, 'throttle:60,1'])->group(function () {
    Route::post('/', [ClubPurchaseController::class, 'store']);
    Route::get('qr', [ClubPurchaseController::class, 'qr']);
    Route::get('{reference}', [ClubPurchaseController::class, 'show'])->whereUuid('reference');
    Route::post('{reference}/receipt', [ClubPurchaseController::class, 'receipt'])->whereUuid('reference');
    Route::post('{reference}/identity', [ClubPurchaseController::class, 'identity'])->whereUuid('reference');
});

// Same backend purchase HMAC, with a distinct canonical request path. No browser session.
Route::post('/internal/club/origins/resolve', [\App\Http\Controllers\ComOriginController::class, 'resolve'])
    ->middleware([AuthenticateClubPurchase::class, 'throttle:60,1']);
