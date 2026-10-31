<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipBankQr;
use App\Services\Meta\MetaBrowserContext;
use App\Services\Meta\MetaEventMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MembershipBankController extends Controller
{
    public function show(Request $request, MembershipBankCheckout $checkout)
    {
        if (! $request->user()) {
            // Use the existing allowed membership return path without changing SSO.
            $request->session()->put('url.intended', url('/membresia?checkout=bank'));

            return redirect('/login');
        }
        $payment = $checkout->start($request->user());

        $metaBrowser = null;
        if ($payment->wasRecentlyCreated) {
            $event = AnalyticsEvent::where('event_id', $payment->checkout_event_id)->first();
            $descriptor = $event ? app(MetaBrowserContext::class)->descriptor($event) : null;
            if ($descriptor) {
                $metaBrowser = ['provider' => 'META', 'pixel_id' => config('meta.pixel_id'), 'events' => [$descriptor],
                    'cta' => app(MetaEventMapper::class)->map('landing_cta_click')];
            }
        }

        return response()->view('membership-bank.checkout', ['buyer' => $request->user(), 'recipient' => app(MembershipBankQr::class)->recipient(), 'metaBrowser' => $metaBrowser, 'payment' => $payment, 'ready' => $checkout->ready(), 'active' => $payment->membership?->isActive() ?? false])->header('Cache-Control', 'private, no-store');
    }

    public function qr(MembershipBankQr $qr)
    {
        return $qr->response();
    }

    public function downloadQr(MembershipBankQr $qr)
    {
        return $qr->response(true);
    }

    public function submit(Request $request, MembershipBankCheckout $checkout)
    {
        abort_unless($checkout->ready(), 422, 'Los cobros aún no están habilitados.');
        $data = $request->validate(['receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'declared_reference' => ['nullable', 'string', 'max:255'], 'paid_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'paid_time' => ['required', 'date_format:H:i'], 'origin_bank' => ['nullable', 'string', 'max:255'], 'payer_name' => ['nullable', 'string', 'max:255']]);
        $path = null;
        try {
            DB::transaction(function () use ($request, $checkout, $data, &$path) {
                User::lockForUpdate()->findOrFail($request->user()->id);
                $payment = MembershipBankPayment::where('user_id', $request->user()->id)->whereIn('status', ['awaiting_payment', 'rejected'])->lockForUpdate()->first();
                abort_unless($payment, 422, 'No hay una solicitud que admita correcciones.');
                $file = $request->file('receipt');
                $path = $file->storeAs('receipts/'.$payment->reference, (string) Str::uuid().'.'.$file->extension(), 'membership-private');
                $payment->fill(collect($data)->except('receipt')->all())->forceFill(['declared_reference' => MembershipBankCheckout::reference($data['declared_reference'] ?? null),
                    'receipt_path' => $path, 'receipt_mime' => $file->getMimeType(), 'status' => 'under_review', 'submitted_at' => now(),
                    'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null])->save();
                $checkout->audit($payment, $request->user(), 'membership_bank_submitted');
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('membership-private')->delete($path);
            }
            throw $e;
        }

        return redirect('/membresia/checkout');
    }

    public function index(Request $request, MembershipBankCheckout $checkout)
    {
        $payments = MembershipBankPayment::with(['user:id,name,email,global_subject_id', 'user.profile:user_id,whatsapp'])->orderByRaw("CASE WHEN status='under_review' THEN 0 ELSE 1 END")->latest('id')->paginate(40);
        $matches = [];
        foreach ($payments as $payment) {
            if ($payment->status === 'under_review') {
                $matches[$payment->id] = $checkout->match($payment);
            }
        }
        $movements = DB::table('membership_bank_movements')->where('amount', '100.00')->whereNotIn('id', MembershipBankPayment::whereNotNull('bank_movement_id')->select('bank_movement_id'))->latest('id')->paginate(50, ['*'], 'movements_page');

        return response()->view('membership-bank.admin', ['qrConfigured' => app(MembershipBankQr::class)->ready(), 'recipient' => app(MembershipBankQr::class)->recipient(), ...compact('payments', 'matches', 'movements')])->header('Cache-Control', 'private, no-store');
    }

    public function import(Request $request, MembershipBankImport $import)
    {
        $data = $request->validate(['statement' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], 'account_key' => ['required', 'regex:/\A[A-Z0-9_-]{1,100}\z/']]);
        $result = $import->import($request->file('statement'), $data['account_key'], $request->user());

        return back()->with('status', $result['duplicate'] ? 'Extracto ya importado.' : $result['imported'].' ingresos nuevos importados.');
    }

    public function approve(Request $request, MembershipBankCheckout $checkout)
    {
        $data = $request->validate(['payment_id' => ['required', 'integer'], 'movement_id' => ['required', 'integer']]);
        $checkout->approve($data['payment_id'], $data['movement_id'], $request->user());

        return back()->with('status', 'Pago conciliado y membresía activada.');
    }

    public function batch(Request $request, MembershipBankCheckout $checkout)
    {
        $data = $request->validate(['payments' => ['required', 'array', 'min:1', 'max:100'], 'payments.*' => ['integer', 'distinct']]);
        DB::transaction(function () use ($data, $checkout, $request) {
            foreach (collect($data['payments'])->sort()->all() as $id) {
                $p = MembershipBankPayment::findOrFail($id);
                $match = $checkout->match($p);
                abort_unless($p->status === 'under_review' && $match['classification'] === 'strong_unique', 422, 'Seleccione únicamente coincidencias fuertes y únicas.');
                $checkout->approve($p->id, $match['candidates'][0]->id, $request->user(), true);
            }
        });

        return back()->with('status', 'Lote conciliado y membresías activadas.');
    }

    public function review(Request $request, MembershipBankPayment $payment, MembershipBankCheckout $checkout)
    {
        $data = $request->validate(['decision' => ['required', 'in:rejected,no_match'], 'note' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($payment, $data, $request, $checkout) {
            $p = MembershipBankPayment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($p->status === 'under_review', 422);
            $p->update(['status' => $data['decision'] === 'rejected' ? 'rejected' : 'under_review', 'review_note' => $data['note'],
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $checkout->audit($p, $request->user(), 'membership_bank_'.$data['decision'], ['note' => $data['note']]);
        });

        return back()->with('status', 'Revisión registrada.');
    }

    public function receipt(Request $request, MembershipBankPayment $payment, MembershipBankCheckout $checkout)
    {
        abort_unless($payment->receipt_path, 404);
        $checkout->audit($payment, $request->user(), 'membership_bank_receipt_viewed');

        return Storage::disk('membership-private')->download($payment->receipt_path, 'comprobante-'.$payment->reference.'.'.pathinfo($payment->receipt_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => $payment->receipt_mime]);
    }
}
