<?php

namespace App\Http\Controllers;

use App\Models\DigitalProduct;
use App\Services\DigitalCatalog;
use App\Services\DigitalCollection;
use App\Services\DigitalMeasurement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DigitalProductController extends Controller
{
    public function index(Request $request, DigitalCatalog $catalog)
    {
        $data = $request->validate(['q' => 'nullable|string|max:200']);
        $page = $catalog->query(trim($data['q'] ?? ''))->paginate(18)->withQueryString();

        return Inertia::render('digital/index', ['products' => $page->through(fn ($p) => $catalog->present($p)), 'query' => $data['q'] ?? '']);
    }

    public function show(Request $request, DigitalProduct $product, DigitalCatalog $catalog)
    {
        abort_unless(DigitalProduct::published()->whereKey($product->id)->exists(), 404);
        app(DigitalMeasurement::class)->record('digital_product_view', $product, once: false, userId: $request->user()?->id);

        return Inertia::render('digital/show', ['product' => $catalog->present($product), 'prelaunch' => (bool) config('jakawi.prelaunch_mode'), 'canCollect' => app(DigitalCollection::class)->readyFor($product->currency), 'authenticated' => $request->user() !== null, 'memberActive' => $request->user()?->hasActiveMembership() ?? false]);
    }
}
