<?php

namespace App\Http\Controllers;
use App\Discovery\DiscoveryService;
use App\Models\DigitalProduct;
use App\Models\DigitalOrder;
use App\Services\DigitalCatalog;
use App\Services\DigitalCollection;
use App\Services\DigitalMeasurement;
use App\Services\SelectedCity;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DigitalProductController extends Controller
{
    public function index(Request $request, DigitalCatalog $catalog)
    {
        $request->query->set('type', 'digital');

        return app(PublicController::class)->explore($request, app(DiscoveryService::class), app(SelectedCity::class));
    }

    public function preview(Request $request, DigitalProduct $product, DigitalCatalog $catalog)
    {
        return Inertia::render('digital/show', ['product' => $catalog->present($product, imagePreset: 'digital_artwork'), 'preview' => true, 'canCollect' => false, 'authenticated' => true, 'memberActive' => false]);
    }

    public function show(Request $request, DigitalProduct $product, DigitalCatalog $catalog)
    {
        abort_unless(DigitalProduct::published()->whereKey($product->id)->exists(), 404);
        app(DigitalMeasurement::class)->record('digital_product_view', $product, once: false, userId: $request->user()?->id);

        // Match checkout's idempotent continuation without modifying an existing order.
        $pendingOrderPrice = $request->user() ? DigitalOrder::where('user_id', $request->user()->id)
            ->where('digital_product_id', $product->id)->where('status', 'pending')
            ->where(fn ($query) => $query->where('expires_at', '>', now())->orWhereHas('payments', fn ($payments) => $payments->where('status', 'under_review')))
            ->value('amount') : null;

        return Inertia::render('digital/show', ['pendingOrderPrice' => $pendingOrderPrice, 'giftAction' => app(\App\Services\DigitalGifts::class)->context($request, $product), 'product' => $catalog->present($product, imagePreset: 'digital_artwork'), 'canCollect' => app(\App\Services\DigitalSaleAvailability::class)->canSell($product), 'authenticated' => $request->user() !== null, 'memberActive' => $request->user()?->hasActiveMembership() ?? false]);
    }
}
