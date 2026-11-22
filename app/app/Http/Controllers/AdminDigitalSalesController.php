<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\DigitalAccessGrant;
use App\Models\DigitalOrder;
use App\Models\DigitalPayment;
use App\Services\DigitalCheckout;
use App\Services\DigitalCollection;
use App\Services\DigitalFiles;
use App\Support\PrelaunchOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminDigitalSalesController extends Controller
{
    public function settings(DigitalCollection $collection)
    {
        $v = $collection->settings();
        if ($v) {
            unset($v['qr_path'],$v['qr_sha256']);
        }

        return Inertia::render('admin/digital/settings', ['settings' => $v, 'ready' => $collection->current() !== null]);
    }

    public function saveSettings(Request $request, DigitalCollection $collection, DigitalFiles $files)
    {
        $data = $request->validate(['bank' => 'required|string|max:150', 'account' => 'required|string|max:100', 'holder' => 'required|string|max:200', 'currency' => 'required|in:BOB,USD', 'enabled' => 'required|boolean', 'exclusive_account' => 'accepted', 'variable_amount_qr' => 'accepted', 'qr' => 'nullable|image|mimes:jpg,jpeg,png|max:10240']);
        unset($data['qr']);
        $file = $request->hasFile('qr') ? $files->store($request->file('qr'), 'qr') : null;
        DB::transaction(function () use ($request, $data, $file) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))', [DigitalCollection::KEY]);
            $setting = AppSetting::where('key', DigitalCollection::KEY)->lockForUpdate()->first();
            $old = $setting?->value ?? [];
            abort_unless($file || ! empty($old['qr_path']), 422, 'Sube el QR propio de JAKAWI Digital.');
            $normalized = preg_replace('/[\s-]+/', '', mb_strtoupper($data['account']));
            $usedForMembership = DB::table('membership_bank_imports')->whereRaw("regexp_replace(upper(account_key), '[[:space:]-]+', '', 'g') = ?", [$normalized])->orWhereRaw("regexp_replace(upper(document_metadata->>'account'), '[[:space:]-]+', '', 'g') = ?", [$normalized])->exists();
            abort_if($usedForMembership, 422, 'Esta cuenta figura en los extractos de membresías. Usa una cuenta exclusiva de JAKAWI Digital.');
            $key = hash('sha256', mb_strtoupper(trim($data['bank'])).'|'.preg_replace('/[\s-]+/', '', mb_strtoupper($data['account'])));
            $v = $data + ['exclusive_account' => true, 'variable_amount_qr' => true, 'version' => (string) Str::uuid(), 'account_key' => $key, 'qr_path' => $file['path'] ?? $old['qr_path'], 'qr_sha256' => $file['sha256'] ?? $old['qr_sha256']];
            AppSetting::updateOrCreate(['key' => DigitalCollection::KEY], ['value' => $v]);
            AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'digital_collection_configured', 'subject_type' => AppSetting::class, 'subject_id' => AppSetting::where('key', DigitalCollection::KEY)->value('id'), 'metadata' => ['version' => $v['version'], 'enabled' => $v['enabled'], 'exclusive_account' => true]]);
        });

        return back()->with('success', 'Configuración digital guardada. PRELAUNCH sigue aplicándose.');
    }

    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:pending,confirmed,refunded,expired']);
        $q = DigitalOrder::with(['user:id,name,email', 'access'])->latest('id');
        if ($data['status'] ?? null) {
            $q->where('status', $data['status']);
        }
        if ($data['q'] ?? null) {
            $q->where(fn ($q) => $q->where('reference', 'ilike', '%'.addcslashes($data['q'], '\\%_').'%')->orWhere('product_name', 'ilike', '%'.addcslashes($data['q'], '\\%_').'%'));
        }
        $confirmed = DigitalPayment::whereIn('status', ['confirmed', 'refunded'])->selectRaw('currency,sum(received_amount) as gross,sum(COALESCE(refund_amount,0)) as refunds')->groupBy('currency')->get();

        return Inertia::render('admin/digital/orders', ['orders' => $q->paginate(25)->withQueryString(), 'totals' => $confirmed]);
    }

    public function show(DigitalOrder $order)
    {
        return Inertia::render('admin/digital/order', ['order' => $order->toArray() + ['collection' => array_intersect_key($order->collection_snapshot, array_flip(['bank', 'account', 'holder', 'currency', 'account_key'])), 'buyer' => $order->user->only('name', 'email'), 'payments' => $order->payments()->orderByDesc('id')->get(), 'access' => $order->access], 'audit' => AuditLog::where('subject_type', DigitalOrder::class)->where('subject_id', $order->id)->latest('id')->get(['id', 'action', 'metadata', 'created_at']), 'prelaunch' => PrelaunchOperations::blocked()]);
    }

    public function file(DigitalPayment $payment, string $kind, DigitalFiles $files)
    {
        abort_unless(in_array($kind, ['receipt', 'statement']), 404);
        $path = $payment->{$kind.'_path'};
        abort_unless($path, 404);

        return $files->response($path, $kind.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }

    public function confirm(Request $request, DigitalPayment $payment, DigitalCheckout $checkout)
    {
        PrelaunchOperations::assertAllowed();
        $data = $request->validate(['bank_reference' => 'required|string|max:150|regex:/^[A-Za-z0-9][A-Za-z0-9._-]{3,149}$/', 'bank_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'received_amount' => 'required|decimal:0,2|gt:0', 'currency' => 'required|in:BOB,USD', 'account_key' => 'required|string|size:64', 'bank_verified' => 'accepted', 'statement' => 'required|file|mimes:pdf|max:25600']);
        $data['statement'] = $request->file('statement');
        $checkout->confirm($payment, $request->user(), $data);

        return back()->with('success', 'Pago confirmado; consulta el estado de entrega.');
    }

    public function reject(Request $request, DigitalPayment $payment, DigitalCheckout $checkout)
    {
        PrelaunchOperations::assertAllowed();
        $data = $request->validate(['reason' => 'required|string|max:3000']);
        $checkout->reject($payment, $request->user(), $data['reason']);

        return back();
    }

    public function retry(Request $request, DigitalAccessGrant $access, DigitalCheckout $checkout)
    {
        $checkout->deliver($access, $request->user());

        return back();
    }

    public function refund(Request $request, DigitalOrder $order, DigitalCheckout $checkout)
    {
        PrelaunchOperations::assertAllowed();
        $data = $request->validate(['reference' => 'required|string|max:150', 'reason' => 'required|string|max:3000', 'refund_completed' => 'accepted']);
        $checkout->refund($order, $request->user(), $data['reference'], $data['reason']);

        return back()->with('success', 'Devolución registrada; acceso revocado.');
    }

    public function incident(Request $request, DigitalOrder $order, DigitalCheckout $checkout)
    {
        $data = $request->validate(['reason' => 'required|string|max:3000']);
        $checkout->audit($request->user(),'digital_incident_recorded',$order,['reason' => $data['reason']]);

        return back();
    }
}
