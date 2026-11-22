<?php

use App\Http\Controllers\AdminDigitalProductController;
use App\Http\Controllers\AdminDigitalSalesController;
use App\Http\Controllers\DigitalOrderController;
use App\Http\Controllers\DigitalProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/productos-digitales', [DigitalProductController::class, 'index'])->name('digital.index');
    Route::get('/productos-digitales/{product:slug}', [DigitalProductController::class, 'show'])->name('digital.show');
    Route::middleware(['auth', 'throttle:30,1'])->group(function () {
        Route::get('/productos-digitales/{product:slug}/comprar', [DigitalProductController::class, 'show'])->name('digital.buy');
        Route::post('/productos-digitales/{product:slug}/comprar', [DigitalOrderController::class, 'start'])->name('digital.orders.start');
        Route::get('/mi-jakawi/productos-digitales', [DigitalOrderController::class, 'library'])->name('digital.library');
        Route::get('/mi-jakawi/productos-digitales/pedidos/{order:reference}', [DigitalOrderController::class, 'show'])->name('digital.orders.show');
        Route::get('/mi-jakawi/productos-digitales/pedidos/{order:reference}/qr', [DigitalOrderController::class, 'qr'])->name('digital.orders.qr');
        Route::post('/mi-jakawi/productos-digitales/pedidos/{order:reference}/comprobante', [DigitalOrderController::class, 'report'])->name('digital.orders.report');
        Route::get('/mi-jakawi/productos-digitales/accesos/{access}/pdf', [DigitalOrderController::class, 'download'])->name('digital.download');
    });
    Route::middleware(['auth', 'admin', 'throttle:60,1'])->prefix('admin/digital')->group(function () {
        Route::get('/cobro/qr', [AdminDigitalSalesController::class, 'collectionQr'])->name('admin.digital.collection.qr');
        Route::get('/cobro', [AdminDigitalSalesController::class, 'settings'])->name('admin.digital.collection');
        Route::post('/cobro', [AdminDigitalSalesController::class, 'saveSettings'])->name('admin.digital.collection.save');
        Route::get('/pedidos', [AdminDigitalSalesController::class, 'index'])->name('admin.digital.orders');
        Route::get('/pedidos/{order:reference}', [AdminDigitalSalesController::class, 'show'])->name('admin.digital.orders.show');
        Route::post('/pedidos/{order:reference}/devolucion', [AdminDigitalSalesController::class, 'refund'])->name('admin.digital.refund');
        Route::post('/pedidos/{order:reference}/incidencia', [AdminDigitalSalesController::class, 'incident'])->name('admin.digital.incident');
        Route::get('/pagos/{payment}/{kind}', [AdminDigitalSalesController::class, 'file'])->whereIn('kind', ['receipt', 'statement'])->name('admin.digital.file');
        Route::post('/pagos/{payment}/confirmar', [AdminDigitalSalesController::class, 'confirm'])->name('admin.digital.confirm');
        Route::post('/pagos/{payment}/rechazar', [AdminDigitalSalesController::class, 'reject'])->name('admin.digital.reject');
        Route::post('/accesos/{access}/reintentar', [AdminDigitalSalesController::class, 'retry'])->name('admin.digital.retry');
    });
    Route::middleware(['auth', 'admin'])->prefix('admin/productos-digitales')->group(function () {
        Route::get('/', [AdminDigitalProductController::class, 'index'])->name('admin.digital.index');
        Route::get('/crear', [AdminDigitalProductController::class, 'edit'])->name('admin.digital.create');
        Route::post('/', [AdminDigitalProductController::class, 'save'])->name('admin.digital.store');
        Route::get('/{product:slug}/preview', [DigitalProductController::class, 'preview'])->name('admin.digital.preview');
        Route::get('/{digitalProduct}/landings', [\App\Http\Controllers\AdminLandingPresentationController::class, 'index'])->name('admin.digital-landing-presentations.index');
        Route::post('/{digitalProduct}/landings', [\App\Http\Controllers\AdminLandingPresentationController::class, 'save'])->name('admin.digital-landing-presentations.store');
        Route::put('/{digitalProduct}/landings/{presentation:slug}', [\App\Http\Controllers\AdminLandingPresentationController::class, 'save'])->name('admin.digital-landing-presentations.update');
        Route::get('/{digitalProduct}/landings/{presentation:slug}/preview', [\App\Http\Controllers\LandingPresentationController::class, 'preview'])->name('admin.digital-landing-presentations.preview');
        Route::post('/{digitalProduct}/landings/{presentation:slug}/publish', [\App\Http\Controllers\AdminLandingPresentationController::class, 'publish'])->name('admin.digital-landing-presentations.publish');
        Route::post('/{digitalProduct}/landings/{presentation:slug}/archive', [\App\Http\Controllers\AdminLandingPresentationController::class, 'archive'])->name('admin.digital-landing-presentations.archive');
        Route::post('/{digitalProduct}/landings/{presentation:slug}/default', [\App\Http\Controllers\AdminLandingPresentationController::class, 'useDefault'])->name('admin.digital-landing-presentations.default');
        Route::post('/{digitalProduct}/landings/{presentation:slug}/duplicate', [\App\Http\Controllers\AdminLandingPresentationController::class, 'duplicate'])->name('admin.digital-landing-presentations.duplicate');
        Route::post('/{digitalProduct}/landings/{presentation:slug}/unpublish', [\App\Http\Controllers\AdminLandingPresentationController::class, 'unpublish'])->name('admin.digital-landing-presentations.unpublish');
        Route::post('/{digitalProduct}/landings/default-product', [\App\Http\Controllers\AdminLandingPresentationController::class, 'productDefault'])->name('admin.digital-landing-presentations.default-product');
        Route::get('/{product:slug}/editar', [AdminDigitalProductController::class, 'edit'])->name('admin.digital.edit');
        Route::post('/{product:slug}', [AdminDigitalProductController::class, 'save'])->name('admin.digital.update');
        Route::get('/{product:slug}/pdf', [AdminDigitalProductController::class, 'pdf'])->name('admin.digital.pdf');
    });
});
