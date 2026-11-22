<?php

namespace App\Http\Controllers;

use App\Discovery\DiscoveryService;
use App\Models\DigitalProduct;
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
        return Inertia::render('digital/show', ['product' => $catalog->present($product), 'preview' => true, 'prelaunch' => (bool) config('jakawi.prelaunch_mode'), 'canCollect' => false, 'authenticated' => true, 'memberActive' => false]);
    }

    public function show(Request $request, DigitalProduct $product, DigitalCatalog $catalog)
    {
        abort_unless(DigitalProduct::published()->whereKey($product->id)->exists(), 404);
        app(DigitalMeasurement::class)->record('digital_product_view', $product, once: false, userId: $request->user()?->id);

        return Inertia::render('digital/show', ['product' => $catalog->present($product), 'prelaunch' => (bool) config('jakawi.prelaunch_mode'), 'canCollect' => app(DigitalCollection::class)->readyFor($product->currency), 'authenticated' => $request->user() !== null, 'memberActive' => $request->user()?->hasActiveMembership() ?? false]);
    }
}
