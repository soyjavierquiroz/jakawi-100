<?php

use App\Http\Controllers\Identity\OidcDiscoveryController;
use App\Http\Controllers\Identity\OidcTokenController;
use App\Http\Middleware\ValidateOidcAuthorization;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use OpenIDConnect\Laravel\JwksController;

Route::get('/.well-known/openid-configuration', OidcDiscoveryController::class)->name('openid.discovery');
Route::get('/oauth/jwks', JwksController::class)->name('openid.jwks');
Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->middleware(['web', 'throttle:oidc-authorize', ValidateOidcAuthorization::class])
    ->name('passport.authorizations.authorize');
// No web/session middleware: the confidential client authenticates at this endpoint.
Route::post('/oauth/token', [OidcTokenController::class, 'issueToken'])
    ->middleware('throttle:oidc-token')->name('passport.token');
