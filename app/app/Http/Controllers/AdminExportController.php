<?php

namespace App\Http\Controllers;

use App\Models\AttributionTouch;
use App\Models\AuditLog;
use App\Models\Conversion;
use App\Models\RewardPayout;
use App\Models\RewardTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExportController extends Controller
{
    public function download(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['conversions', 'rewards', 'payouts', 'attribution'], true), 404);
        $filters = $request->only('from', 'to', 'status', 'participant_type', 'campaign');
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'admin_csv_exported', 'subject_type' => 'csv_export', 'metadata' => ['type' => $type, 'filters' => $filters]]);
        [$headers, $rows] = $this->{$type}($request);
        return response()->streamDownload(function () use ($headers, $rows) { $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF"); fputcsv($out, $headers); foreach ($rows as $row) fputcsv($out, array_map([$this, 'safe'], $row)); fclose($out); }, $type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function conversions(Request $request): array
    {
        $q = Conversion::with(['campaign:id,name', 'relationship'])->orderBy('id'); $this->filters($q, $request, 'occurred_at'); if ($request->filled('campaign')) $q->where('campaign_id', $request->integer('campaign'));
        return [['conversion id/reference', 'user internal id', 'type', 'product', 'gross amount', 'eligible amount', 'currency', 'status', 'occurred at', 'campaign', 'credited beneficiary type/id', 'acquisition partner id'], $q->cursor()->map(fn (Conversion $c) => [$c->order_reference ?: $c->id, $c->user_id, $c->type, $c->product_key ?: $c->product_type, $c->gross_amount, $c->eligible_amount, $c->currency, $c->status, $c->occurred_at, $c->campaign?->name, $c->relationship ? (($c->relationship->referrer_user_id ? 'USER:' . $c->relationship->referrer_user_id : 'PARTNER:' . $c->relationship->acquisition_partner_id)) : null, $c->relationship?->acquisition_partner_id])];
    }
    private function rewards(Request $request): array
    {
        $q = RewardTransaction::operational()->with(['conversion:id,order_reference,campaign_id', 'rule:id,campaign_id'])->orderBy('id'); $this->filters($q, $request, 'created_at');
        if ($request->filled('participant_type')) $q->whereHas('rule', fn ($x) => $x->where('participant_type', $request->string('participant_type')));
        if ($request->filled('campaign')) $q->whereHas('rule', fn ($x) => $x->where('campaign_id', $request->integer('campaign')));
        return [['reward id', 'beneficiary type', 'beneficiary id', 'participant type', 'reward type', 'amount', 'currency/unit', 'status', 'conversion', 'rule', 'campaign', 'created at'], $q->cursor()->map(fn (RewardTransaction $r) => [$r->id, $r->beneficiary_type, $r->beneficiary_id, $r->rule?->participant_type, $r->reward_type, $r->amount, $r->currency ?: $r->reward_type, $r->status, $r->conversion?->order_reference ?: $r->conversion_id, $r->reward_rule_id, $r->rule?->campaign_id, $r->created_at])];
    }
    private function payouts(Request $request): array
    {
        $q = RewardPayout::where('beneficiary_type', 'PARTNER')->orderBy('id'); $this->filters($q, $request, 'requested_at');
        return [['payout reference', 'beneficiary', 'amount', 'currency', 'status', 'requested at', 'paid at', 'payment reference', 'paid by admin id'], $q->cursor()->map(fn (RewardPayout $p) => [$p->reference, ($p->beneficiary_type ?: 'USER').':'.($p->beneficiary_id ?: $p->beneficiary_user_id), $p->requested_amount, $p->currency, $p->status, $p->requested_at, $p->paid_at, $p->payment_reference, $p->paid_by_user_id])];
    }
    private function attribution(Request $request): array
    {
        $q = AttributionTouch::orderBy('id'); $this->filters($q, $request, 'occurred_at');
        if ($request->filled('campaign')) $q->where('utm_campaign', $request->string('campaign'));
        return [['user internal id', 'anonymous/internal attribution id', 'first referrer type/id', 'partner acquisition id', 'utm source', 'utm medium', 'utm campaign', 'utm content', 'landing page', 'occurred at'], $q->cursor()->map(fn (AttributionTouch $t) => [$t->user_id, $t->anonymous_id ?: $t->id, $t->referrer_user_id ? 'USER:'.$t->referrer_user_id : null, $t->acquisition_partner_id, $t->utm_source, $t->utm_medium, $t->utm_campaign, $t->utm_content, $t->landing_page, $t->occurred_at])];
    }
    private function filters(Builder $q, Request $r, string $date): void { if ($r->filled('from')) $q->whereDate($date, '>=', $r->date('from')); if ($r->filled('to')) $q->whereDate($date, '<=', $r->date('to')); if ($r->filled('status')) $q->where('status', $r->string('status')->toString()); }
    private function safe(mixed $value): mixed { $value = (string) $value; return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value; }
}
