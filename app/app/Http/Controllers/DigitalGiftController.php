<?php

namespace App\Http\Controllers;

use App\Models\{DigitalGift, DigitalProduct, User};
use App\Services\{DigitalCatalog, DigitalGifts};
use Illuminate\Http\Request;
use Inertia\Inertia;

class DigitalGiftController extends Controller
{
    public function invitation(Request $request, string $token, DigitalGifts $gifts)
    {
        $gifts->assertEnabled();
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $hash = hash('sha256', $token);
        $gift = DigitalGift::where('token_hash', $hash)->where('kind', 'promotional')->where('status', 'pending')->where('expires_at', '>', now())->first();
        abort_unless($gift, 410, 'Este enlace venció o ya fue reclamado.');
        // Identity restrictions are user IDs, never administratively verified emails.
        abort_if($request->user() && $gift->user_id && $gift->user_id !== $request->user()->id, 403);
        $request->session()->put('digital_gift_hash', $hash);
        $product = DigitalProduct::findOrFail($gift->digital_product_id);
        return redirect('/productos-digitales/'.$product->slug)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function continue(Request $request, DigitalGifts $gifts)
    {
        $gift = $gifts->invitation($request);
        abort_unless($gift, 410, 'Este regalo no está disponible para esta cuenta.');
        return redirect('/productos-digitales/'.DigitalProduct::findOrFail($gift->digital_product_id)->slug);
    }

    public function welcome(Request $request, DigitalProduct $product, DigitalGifts $gifts)
    {
        $request->validate(['confirm' => 'required|accepted']);
        $gifts->claim($request->user(), $product);
        return redirect('/mi-jakawi/productos-digitales')->with('success', '¡Tu regalo está listo!');
    }

    public function promotional(Request $request, DigitalProduct $product, DigitalGifts $gifts)
    {
        $request->validate(['confirm' => 'required|accepted']);
        $hash = $request->session()->get('digital_gift_hash');
        abort_unless(is_string($hash), 403);
        $gifts->claim($request->user(), $product, $hash);
        $request->session()->forget('digital_gift_hash');
        return redirect('/mi-jakawi/productos-digitales')->with('success', '¡Tu regalo está listo!');
    }

    public function admin(Request $request, DigitalProduct $product, DigitalCatalog $catalog, DigitalGifts $gifts)
    {
        $catalog->assertInternal($product);
        $request->validate(['q' => 'nullable|string|max:200']);
        $term = trim((string) $request->query('q', ''));
        $users = $term !== '' ? User::where(fn ($q) => $q->where('name', 'ilike', '%'.addcslashes($term, '\\%_').'%')->orWhere('email', 'ilike', '%'.addcslashes($term, '\\%_').'%'))->where('email', 'not like', '%@purchases.invalid')->limit(15)->get(['id','name','email']) : [];
        return Inertia::render('admin/digital/gift', ['product' => $product->only('name','slug'), 'users' => $users, 'query' => $term,
            'enabled' => $gifts->enabled(), 'ready' => $gifts->eligible($product, 'promotional')]);
    }

    public function issue(Request $request, DigitalProduct $product, DigitalCatalog $catalog, DigitalGifts $gifts)
    {
        $catalog->assertInternal($product);
        $data = $request->validate(['user_id' => 'nullable|integer|exists:users,id', 'issuance_key' => 'required|uuid']);
        $recipient = isset($data['user_id']) ? User::findOrFail($data['user_id']) : null;
        abort_if($recipient && str_ends_with(strtolower($recipient->email), '@purchases.invalid'), 422);
        $invitation = $gifts->issue($product, $request->user(), $recipient, $data['issuance_key']);
        return response()->json($invitation, 201)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
