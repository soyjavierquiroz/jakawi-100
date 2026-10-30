<?php

use App\Http\Controllers\PublicCatalogController;
use Illuminate\Support\Facades\Route;

// Stateless public reads: no visitor, attribution, session or commercial writes.
Route::get('/api/catalog/v1/offers', PublicCatalogController::class)->name('catalog.offers');
