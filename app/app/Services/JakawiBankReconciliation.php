<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\MembershipBankPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Decisions only. Activation, identity and the commercial event belong to COM checkout. */
class JakawiBankReconciliation
{
    public static function normalized(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtoupper(Str::ascii($value ?? ''))));
    }

    private function contains(string $text, ?string $value): bool
    {
        $value = self::normalized($value);
        return strlen($value) >= 6 && preg_match('/(?<![A-Z0-9])'.preg_quote($value, '/').'(?![A-Z0-9])/', $text) === 1;
    }

    public function movements()
    {
        return DB::table('membership_bank_movements')->where('credit', '>', 0)->where('debit', 0)
            ->whereRaw("upper(regexp_replace(description, '\s+', ' ', 'g')) LIKE '%JAKAWI%'");
    }

    public function decision(object $m): array
    {
        $e = ['version'=>2, 'movement_id'=>$m->id, 'import_id'=>$m->import_id, 'amount'=>$m->amount,
            'date'=>$m->paid_date, 'time'=>$m->paid_time, 'reference'=>$m->reference, 'candidates'=>[]];
        $result = fn($status, $reason, $id=null) => ['status'=>$status, 'reason'=>$reason, 'payment_id'=>$id, 'evidence'=>$e];
        $used = MembershipBankPayment::where('bank_movement_id', $m->id)->first();
        if ($used) { return $result('reconciled', 'Movimiento ya aplicado; no se vuelve a activar.', $used->id); }
        $import = DB::table('membership_bank_imports')->find($m->import_id);
        $meta = json_decode($import->document_metadata ?? '{}', true);
        $e['account_key'] = $m->account_key; $e['statement_sha256'] = $import->sha256;
        if ($m->reconciliation_hold || $import->processing_status !== 'processed' || !($meta['balance_verified'] ?? false) || ($meta['currency'] ?? '') !== 'BOB') {
            return $result('review_required', 'Identidad bancaria retenida o PDF sin validación íntegra de saldos y moneda.');
        }
        if ($m->amount !== '100.00' || $m->credit !== '100.00') { return $result('review_required', 'Importe distinto de Bs 100.'); }
        $text = self::normalized($m->description);
        $reversals = DB::table('membership_bank_movements')->where('account_key', $m->account_key)
            ->where(function($q) use($m) { $q->where('debit', $m->amount)->orWhereRaw("upper(description) ~ 'REVERS|ANUL|EXTORN|DEVOL'"); })->get();
        foreach ($reversals as $other) {
            if ($other->id === $m->id || ($m->reference && $other->reference === $m->reference)
                || ($other->paid_date === $m->paid_date && preg_match('/REVERS|ANUL|EXTORN|DEVOL/', self::normalized($other->description)))) {
                return $result('review_required', 'Posible reverso, devolución o anulación en la cuenta.');
            }
        }
        $matches = []; $conflicts = [];
        foreach (MembershipBankPayment::with('user')->whereIn('status', ['awaiting_payment','PAYMENT_REPORTED','under_review','rejected'])->orderBy('id')->get() as $p) {
            $d = $p->extraction_json ?? [];
            $direct = $this->contains($text, $p->reference) || $this->contains($text, $p->club_reference);
            $reference = $this->contains($text, $p->declared_reference) || ($p->declared_reference && $m->reference === $p->declared_reference);
            $sameDate = $p->paid_date?->format('Y-m-d') === $m->paid_date;
            $sameTime = $p->paid_time && $m->paid_time && $p->paid_time === $m->paid_time;
            // Distinct ACH and bank transaction IDs are allowed only with independent payer + timestamp evidence.
            $payer = self::normalized($d['payer_name'] ?? '');
            $name = self::normalized($p->buyer_name ?: $p->user?->name);
            $named = count(explode(' ', $payer)) >= 2 && strlen($payer) >= 10 && $payer === $name && $this->contains($text, $payer);
            $receipt = $p->receipt_path && in_array($p->extraction_status, ['completed','manual'], true)
                && app(MembershipReceiptVision::class)->supportsStrongMatch($p)
                && !empty($d['recipient']) && app(MembershipReceiptVision::class)->recipientMatches($d['recipient']);
            $independent = $this->independentReceiptMatch($p, $m);
            $identified = $direct || ($receipt && $sameDate && ($reference || ($sameTime && $named) || $independent));
            // A claimed reference with contradictory evidence must block another buyer's automatic assignment too.
            if (!$identified && !$reference) { continue; }
            $originBank = self::normalized($d['origin_bank'] ?? '');
            $bankLines = array_values(array_filter(array_map(fn($line)=>self::normalized($line), explode("\n", $m->description)), fn($line)=>str_starts_with($line, 'BANCO ')));
            $bankConflict = $originBank !== '' && $bankLines && !$this->bankMatches($originBank, $bankLines);
            $blockers = array_values(array_filter([
                $bankConflict ? 'Banco de origen contradictorio.' : null,
                $m->paid_date < $p->created_at->timezone('America/La_Paz')->format('Y-m-d') ? 'El ingreso precede a la compra.' : null,
                $p->status === 'rejected' ? 'Compra rechazada.' : null,
                $p->amount !== $m->amount || (isset($d['amount']) && $d['amount'] !== $m->amount) ? 'Importe contradictorio.' : null,
                $p->paid_date && !$sameDate ? 'Fecha contradictoria.' : null,
                $p->paid_time && $m->paid_time && !$sameTime && !$independent ? 'Hora diferente sin evidencia conjunta de banco, pagador y cuenta enmascarada dentro de 5 segundos.' : null,
                $p->receipt_path && !$receipt ? 'Comprobante sin extracción válida de importe, moneda, confianza y destinatario completo.' : null,
                isset($d['currency']) && $d['currency'] !== 'BOB' ? 'Moneda contradictoria.' : null,
                !$identified ? 'Falta identificación independiente de la compra.' : null,
            ]));
            $contradiction = $blockers !== [];
            $e['candidates'][] = ['payment_id'=>$p->id, 'direct_purchase_reference'=>$direct, 'bank_reference'=>$reference,
                'receipt_verified'=>(bool)$receipt, 'same_date'=>$sameDate, 'same_time'=>(bool)$sameTime, 'payer_in_statement'=>$named, 'independent_account_bank_payer_time'=>$independent, 'conflict'=>$contradiction, 'bank_conflict'=>$bankConflict, 'blockers'=>$blockers,
                'receipt_sha256'=>$p->receipt_sha256, 'extraction_version'=>$p->extraction_version,
                'receipt_fields'=>array_intersect_key($d,array_flip(MembershipReceiptVision::FIELDS)), 'confidence'=>$d['confidence'] ?? null];
            if ($contradiction || !$identified) { $conflicts[]=$p->id; } else { $matches[]=$p->id; }
        }
        $result = fn($status, $reason, $id=null) => ['status'=>$status,'reason'=>$reason,'payment_id'=>$id,'evidence'=>$e];
        if ($conflicts || count($matches)>1) {
            $single = count($e['candidates']) === 1 ? $e['candidates'][0] : null;
            return $result('review_required', $single ? implode(' ', $single['blockers']) : 'Compradores múltiples o evidencia contradictoria/incompleta.', $single['payment_id'] ?? null);
        }
        if (!$matches) { return $result('unidentified', 'Sin evidencia suficiente para identificar una compra; importe y proximidad horaria no bastan.'); }
        $p = MembershipBankPayment::find($matches[0]);
        if ($p->receipt_sha256 && MembershipBankPayment::whereKeyNot($p->id)
            ->whereIn('status',['awaiting_payment','PAYMENT_REPORTED','under_review','rejected'])
            ->where('receipt_sha256',$p->receipt_sha256)->exists()) {
            return $result('review_required', 'El mismo comprobante está reclamado por otra compra.', $p->id);
        }
        // Bidirectional uniqueness: a receipt or purchase reference cannot consume two credits.
        $compatible = 0;
        foreach ($this->movements()->where('amount', '100.00')->get() as $other) {
            $t = self::normalized($other->description);
            if ($this->contains($t, $p->reference) || $this->contains($t, $p->club_reference)
                || ($p->declared_reference && ($other->reference === $p->declared_reference || $this->contains($t, $p->declared_reference)))
                || $this->independentReceiptMatch($p, $other)
                || ($p->paid_date?->format('Y-m-d') === $other->paid_date && $p->paid_time && $p->paid_time === $other->paid_time
                    && $this->contains($t, $p->payer_name))) { $compatible++; }
        }
        if ($compatible !== 1) { return $result('review_required', 'La compra puede corresponder a varios créditos.'); }
        return $result('safe', 'Crédito validado e identificación única en ambos sentidos, sin contradicciones.', $p->id);
    }

    private function bankMatches(string $bank, array $lines): bool
    {
        $canonical = fn ($v) => match (self::normalized($v)) {
            'BNB', 'BANCO NACIONAL DE BOLIVIA', 'BANCO NACIONAL DE BOLIVIA S.A.' => 'BNB',
            default => self::normalized($v),
        };
        return in_array($canonical($bank), array_map($canonical, $lines), true);
    }

    private function independentReceiptMatch(MembershipBankPayment $p, object $m): bool
    {
        $d = $p->extraction_json ?? [];
        if (!$p->receipt_path || !in_array($p->extraction_status, ['completed','manual'], true)
            || !app(MembershipReceiptVision::class)->supportsStrongMatch($p)
            || empty($d['recipient']) || !app(MembershipReceiptVision::class)->recipientMatches($d['recipient'])
            || $p->paid_date?->format('Y-m-d') !== $m->paid_date || $m->amount !== '100.00'
            || !$p->paid_time || !$m->paid_time) { return false; }
        $seconds = function ($time) {
            if (!preg_match('/\A([01]\d|2[0-3]):([0-5]\d):([0-5]\d)\z/', $time, $parts)) { return null; }
            return (int)$parts[1]*3600 + (int)$parts[2]*60 + (int)$parts[3];
        };
        $a=$seconds($p->paid_time); $b=$seconds($m->paid_time);
        if ($a === null || $b === null || abs($a-$b)>5) { return false; }
        $lines = array_map(fn($line)=>self::normalized($line), explode("\n", $m->description));
        $bankLines = array_values(array_filter($lines, fn($line)=>str_starts_with($line,'BANCO ')));
        if (empty($d['origin_bank']) || !$this->bankMatches($d['origin_bank'], $bankLines)) { return false; }
        // Exact printed payer line, including a bank-truncated name. The buyer may be a different person.
        $payer=self::normalized($d['payer_name'] ?? '');
        if (strlen($payer)<10 || count(explode(' ',$payer))<2 || !in_array($payer,$lines,true)) { return false; }
        $account=strtoupper(preg_replace('/[\s-]+/', '', $d['origin_account'] ?? ''));
        // At least three visible digits at each end, and a mask between. Never infer hidden digits.
        if (!preg_match('/\A(\d{3,})[*X•]+(\d{3,})\z/u', $account, $parts)) { return false; }
        foreach ($lines as $index=>$line) {
            if (!$this->bankMatches($d['origin_bank'], [$line])) { continue; }
            $digits=preg_replace('/[\s-]+/', '', $lines[$index+1] ?? '');
            if (ctype_digit($digits) && strlen($digits)>strlen($parts[1])+strlen($parts[2])
                && str_starts_with($digits,$parts[1]) && str_ends_with($digits,$parts[2])) { return true; }
        }
        return false;
    }

    public function paymentReview(MembershipBankPayment $p): array
    {
        $rows = $this->movements()->where('amount','100.00')
            ->when($p->paid_date, fn($q)=>$q->where('paid_date',$p->paid_date->format('Y-m-d')))
            ->whereNotIn('id', MembershipBankPayment::whereNotNull('bank_movement_id')->select('bank_movement_id'))
            ->orderBy('paid_time')->limit(100)->get();
        $reason = match ($p->extraction_status) {
            'unconfigured' => 'La IA no estaba configurada: reintentar extracción con el archivo recibido.',
            'failed' => 'Extracción fallida: '.($p->extraction_error ?? 'sin código').'. Puede reintentarse.',
            'pending','processing' => 'Esperando la extracción IA del comprobante recibido.',
            default => 'Sin evidencia suficiente; no se aprueba por importe y hora solos.',
        };
        foreach ($rows as $m) {
            $decision=$this->decision($m);
            $m->reconciliation_reason=$decision['reason'];
            if ($decision['payment_id']===$p->id) { $reason=$decision['reason']; }
        }
        return ['candidates'=>$rows->all(), 'reason'=>$reason];
    }

    public function run(): int
    {
        $count=0;
        $this->movements()->orderBy('id')->chunkById(100, function($rows) use (&$count) {
            foreach ($rows as $row) {
                DB::transaction(function() use($row, &$count) {
                    // Blocks concurrent claims, imports and manual approvals while checking the complete candidate set.
                    DB::statement('LOCK TABLE membership_bank_payments, membership_bank_movements IN SHARE ROW EXCLUSIVE MODE');
                    $m=DB::table('membership_bank_movements')->find($row->id);
                    $d=$this->decision($m);
                    $old=DB::table('jakawi_bank_reconciliations')->where('movement_id',$m->id)->first();
                    if ($d['status']==='reconciled' && $old?->status==='auto_reconciled') { return; }
                    if ($d['status']==='safe') {
                        try {
                            DB::transaction(function() use($d,$m) {
                                $admin=User::find(DB::table('membership_bank_imports')->where('id',$m->import_id)->value('admin_id'));
                                abort_unless($admin?->is_admin,422,'Administrador del extracto no disponible.');
                                app(MembershipBankCheckout::class)->approve($d['payment_id'],$m->id,$admin,false,true);
                            });
                            $d['status']='auto_reconciled'; $count++;
                        } catch (\Symfony\Component\HttpKernel\Exception\HttpException|\Illuminate\Validation\ValidationException|\DomainException $ex) {
                            $d['status']='review_required'; $d['reason']='Activación bloqueada por COM: '.$ex->getMessage();
                        }
                    }
                    $data=['status'=>$d['status'],'payment_id'=>$d['payment_id'],'reason'=>$d['reason'],'evidence'=>json_encode($d['evidence'],JSON_THROW_ON_ERROR)];
                    if ($old && $old->status===$data['status'] && $old->reason===$data['reason'] && json_decode($old->evidence,true)==$d['evidence']) { return; }
                    DB::table('jakawi_bank_reconciliations')->updateOrInsert(['movement_id'=>$m->id],[...$data,'created_at'=>$old?->created_at ?? now(),'updated_at'=>now()]);
                    AuditLog::create(['actor_user_id'=>null,'action'=>'jakawi_bank_automatic_decision','subject_type'=>'membership_bank_movements','subject_id'=>$m->id,
                        'metadata'=>['automatic'=>true,...$d]]);
                },5);
            }
        });
        return $count;
    }
}
