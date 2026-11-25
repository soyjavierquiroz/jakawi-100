<?php

namespace App\Http\Controllers;
use App\Models\DigitalAccessGrant;
use App\Models\DigitalOrder;
use App\Models\DigitalProduct;
use App\Services\DigitalCheckout;
use App\Services\DigitalCollection;
use App\Services\DigitalFiles;
use App\Services\DigitalMeasurement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DigitalOrderController extends Controller
{
    public function start(Request $request, DigitalProduct $product, DigitalCheckout $checkout)
    {
        $request->validate(['amount' => 'prohibited', 'price' => 'prohibited', 'currency' => 'prohibited', 'member_price' => 'prohibited', 'member_at_purchase' => 'prohibited', 'membership_id' => 'prohibited', 'qr_tier' => 'prohibited', 'qr_path' => 'prohibited']);
        $order = $checkout->start($request->user(), $product);

        return redirect('/mi-jakawi/productos-digitales/pedidos/'.$order->reference);
    }

    public function show(Request $request, DigitalOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return Inertia::render('digital/order', ['order' => $order->toArray() + ['cover_url' => app(\App\Services\MediaUrl::class)->url($order->product?->cover_path, 'hero'), 'collection' => array_intersect_key($order->collection_snapshot, array_flip(['bank', 'account', 'holder', 'currency'])), 'payments' => $order->payments()->orderByDesc('id')->get(['id', 'status', 'review_note', 'created_at']), 'access' => $order->access?->only(['id', 'status', 'delivery_status'])], 'canCollect' => app(\App\Services\DigitalProductQr::class)->orderReady($order)]);
    }

    public function qr(Request $request, DigitalOrder $order, DigitalFiles $files)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->status === 'pending' && $order->expires_at->isFuture() && app(\App\Services\DigitalProductQr::class)->orderReady($order), 422);

        return $files->response($order->collection_snapshot['qr_path'], 'qr-digital.'.pathinfo($order->collection_snapshot['qr_path'], PATHINFO_EXTENSION), false);
    }

    public function report(Request $request, DigitalOrder $order, DigitalCheckout $checkout)
    {
        $request->validate(['receipt' => 'required|file|mimes:pdf,png,jpg,jpeg|max:25600']);
        $checkout->report($order, $request->user(), $request->file('receipt'));

        return back()->with('success', 'Comprobante recibido. Falta verificar el ingreso bancario.');
    }

    public function library(Request $request)
    {
        $orders = DigitalOrder::where('user_id', $request->user()->id)->with('access')->latest('id')->paginate(25);

        $gifts = DigitalAccessGrant::where('user_id', $request->user()->id)->where('source', 'gift')->latest('id')->paginate(25, ['id','user_id','source','digital_order_id','digital_product_id','gift_product_name','status','delivery_status'], 'gifts_page');

        return Inertia::render('digital/library', ['orders' => $orders, 'giftAccess' => $gifts]);
    }

    public function download(Request $request, DigitalAccessGrant $access, DigitalFiles $files)
    {
        if ($access->source === 'gift') {
            abort_unless($access->user_id === $request->user()->id && $access->status === 'active' && $access->delivery_status === 'ready', 403);
            $bytes = Storage::disk('digital-private')->get($access->gift_file_path);
            abort_unless(is_string($bytes) && hash_equals($access->gift_file_sha256, hash('sha256', $bytes)), 503, 'El PDF no está disponible. Contacta con soporte.');
            return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$access->gift_product_slug.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
        }

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
