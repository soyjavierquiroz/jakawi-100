<?php

namespace App\Http\Controllers;

use App\Models\DigitalAccessGrant;
use App\Models\DigitalOrder;
use App\Models\DigitalProduct;
use App\Services\DigitalCheckout;
use App\Services\DigitalCollection;
use App\Services\DigitalFiles;
use App\Services\DigitalMeasurement;
use App\Support\PrelaunchOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DigitalOrderController extends Controller
{
    public function start(Request $request, DigitalProduct $product, DigitalCheckout $checkout)
    {
        $request->validate(['amount' => 'prohibited', 'price' => 'prohibited', 'currency' => 'prohibited', 'member_price' => 'prohibited']);
        $order = $checkout->start($request->user(), $product);

        return redirect('/mi-jakawi/productos-digitales/pedidos/'.$order->reference);
    }

    public function show(Request $request, DigitalOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return Inertia::render('digital/order', ['order' => $order->toArray() + ['collection' => array_intersect_key($order->collection_snapshot, array_flip(['bank', 'account', 'holder', 'currency'])), 'payments' => $order->payments()->orderByDesc('id')->get(['id', 'status', 'review_note', 'created_at']), 'access' => $order->access?->only(['id', 'status', 'delivery_status'])], 'prelaunch' => ! PrelaunchOperations::digitalAllowed($order), 'canCollect' => app(DigitalCollection::class)->readyFor($order->currency) && app(DigitalCollection::class)->current()['version'] === $order->collection_snapshot['version']]);
    }

    public function qr(Request $request, DigitalOrder $order, DigitalFiles $files)
    {
        PrelaunchOperations::assertDigitalAllowed($order);
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->status === 'pending' && $order->expires_at->isFuture() && app(DigitalCollection::class)->readyFor($order->currency) && app(DigitalCollection::class)->current()['version'] === $order->collection_snapshot['version'], 422);

        return $files->response($order->collection_snapshot['qr_path'], 'qr-digital.'.pathinfo($order->collection_snapshot['qr_path'], PATHINFO_EXTENSION), false);
    }

    public function report(Request $request, DigitalOrder $order, DigitalCheckout $checkout)
    {
        PrelaunchOperations::assertDigitalAllowed($order);
        $request->validate(['receipt' => 'required|file|mimes:pdf,png,jpg,jpeg|max:25600']);
        $checkout->report($order, $request->user(), $request->file('receipt'));

        return back()->with('success', 'Comprobante recibido. Falta verificar el ingreso bancario.');
    }

    public function library(Request $request)
    {
        $orders = DigitalOrder::where('user_id', $request->user()->id)->with('access')->latest('id')->paginate(25);
        $orders->getCollection()->each(fn ($order) => $order->setAttribute('digital_allowed', PrelaunchOperations::digitalAllowed($order)));

        return Inertia::render('digital/library', ['orders' => $orders]);
    }

    public function download(Request $request, DigitalAccessGrant $access, DigitalFiles $files)
    {
        PrelaunchOperations::assertDigitalAllowed($access);

        return DB::transaction(function () use ($request, $access) {
            $order = DigitalOrder::lockForUpdate()->findOrFail($access->digital_order_id);
            $access = DigitalAccessGrant::lockForUpdate()->findOrFail($access->id);
            abort_unless($access->user_id === $request->user()->id && $order->user_id === $request->user()->id && $order->status === 'confirmed' && $access->status === 'active' && $access->delivery_status === 'ready', 403);
            $disk = Storage::disk('digital-private');
            abort_unless($disk->exists($order->file_path), 404);
            // Capture authorized bytes before releasing the lock; refunds cannot race authorization.
            $bytes = $disk->get($order->file_path);
            abort_unless(hash_equals($order->file_sha256, hash('sha256', $bytes)), 503, 'El PDF no está disponible. Contacta con soporte.');
            app(DigitalMeasurement::class)->record('digital_download_requested', $access, $order, false);

            return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$order->product_slug.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
        });
    }
}
