<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractMembershipReceipt;
use App\Models\MembershipBankPayment;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipReceiptVision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminReceiptExtractionController extends Controller
{
    public function update(Request $request, MembershipBankPayment $payment, MembershipReceiptVision $vision, MembershipBankCheckout $checkout)
    {
        $rules = [];
        foreach (MembershipReceiptVision::FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }
        $rules['amount'] = ['nullable', 'regex:/\A\d{1,10}(\.\d{1,2})?\z/'];
        $rules['date'] = ['nullable', 'date_format:Y-m-d'];
        $rules['time'] = ['nullable', 'date_format:H:i:s'];
        $rules['currency'] = ['nullable', 'regex:/\A[A-Z]{3}\z/'];
        $data = $request->validate($rules);
        DB::transaction(function () use ($payment, $vision, $checkout, $data, $request) {
            $p = MembershipBankPayment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($p->status === 'under_review', 422);
            $normalized = $vision->normalize([...$data, 'confidence' => 'high', 'evidence' => $p->extraction_json['evidence'] ?? []]);
            $p->update([...$vision->paymentFields($normalized), 'extraction_status' => 'manual', 'extraction_json' => $normalized, 'extraction_error' => null, 'extraction_processed_at' => now(), 'extraction_claimed_at' => null]);
            $checkout->audit($p, $request->user(), 'membership_receipt_corrected', ['fields' => array_keys($data)]);
        });

        return back()->with('status', 'Datos corregidos. Vuelve a revisar la coincidencia bancaria antes de aprobar.');
    }

    public function retry(Request $request, MembershipBankPayment $payment, MembershipBankCheckout $checkout)
    {
        DB::transaction(function () use ($payment, $request, $checkout) {
            $p = MembershipBankPayment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($p->status === 'under_review' && $p->receipt_path && in_array($p->extraction_status, ['failed', 'unconfigured'], true), 422);
            $version = (string) Str::uuid();
            $p->update(['extraction_version' => $version, 'extraction_status' => 'pending', 'extraction_error' => null, 'extraction_attempts' => 0, 'extraction_claimed_at' => null]);
            ExtractMembershipReceipt::dispatch($p->id, $version);
            $checkout->audit($p, $request->user(), 'membership_receipt_retry');
        });

        return back()->with('status','Extracción en cola. La revisión manual sigue disponible.');
    }
}
