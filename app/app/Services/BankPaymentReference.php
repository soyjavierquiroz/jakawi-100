<?php
namespace App\Services;

use App\Models\DigitalPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cross-check the two existing payment ledgers; never claims or reconciles a payment. */
class BankPaymentReference
{
    private function normalizeAccount(?string $account): string
    {
        return preg_replace('/[\s-]+/', '', mb_strtoupper($account ?? ''));
    }

    private function lock(?string $reference): string
    {
        $reference = mb_strtoupper(trim($reference ?? ''));
        if ($reference !== '') {
            // Shared by manual and automatic approval, including concurrent cross-ledger requests.
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))', ['jakawi-bank-reference:'.$reference]);
        }
        return $reference;
    }

    private function sameAccount(string $digitalAccount, object $movement): bool
    {
        $metadata = json_decode($movement->document_metadata ?? '{}', true);
        $account = $this->normalizeAccount($metadata['account'] ?? $movement->account_key);
        // Legacy aliases without the printed account cannot safely distinguish two identical references.
        return $account === $this->normalizeAccount($digitalAccount)
            || ! ctype_digit($account);
    }

    public function forDigital(array $collection, string $reference): void
    {
        $reference = $this->lock($reference);
        $used = DB::table('membership_bank_payments as p')->join('membership_bank_movements as m','m.id','=','p.bank_movement_id')
            ->join('membership_bank_imports as i','i.id','=','m.import_id')->whereRaw('upper(trim(m.reference)) = ?', [$reference])
            ->get(['m.account_key','i.document_metadata']);
        foreach ($used as $movement) {
            if ($this->sameAccount($collection['account'], $movement)) {
                throw ValidationException::withMessages(['bank_reference'=>'Esta referencia bancaria ya fue aplicada a una membresía. No puede utilizarse también para un producto digital.']);
            }
        }
    }

    public function forMembership(object $movement): void
    {
        $reference = $this->lock($movement->reference);
        if ($reference === '') return;
        $movement->document_metadata = DB::table('membership_bank_imports')->where('id',$movement->import_id)->value('document_metadata');
        // Refunded digital payments retain their bank reference and remain unavailable for reuse.
        foreach (DigitalPayment::with('order')->whereNotNull('bank_account_key')->whereRaw('upper(trim(bank_reference)) = ?', [$reference])->get() as $payment) {
            if ($this->sameAccount($payment->order->collection_snapshot['account'], $movement)) {
                throw ValidationException::withMessages(['movement_id'=>'Esta referencia bancaria ya fue aplicada a un producto digital. No puede utilizarse también para una membresía.']);
            }
        }
    }
}
