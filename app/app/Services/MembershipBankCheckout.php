<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\AuditLog;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Notifications\MembershipBankActivated;
use App\Services\Meta\MetaDeliveryOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipBankCheckout
{
    public function ready(): bool
    {
        return app(MembershipBankQr::class)->ready();
    }

    public static function reference(?string $value): ?string
    {
        $value = mb_strtoupper(trim($value ?? ''));

        return $value === '' ? null : $value;
    }

    public function start(User $user): MembershipBankPayment
    {
        return DB::transaction(function () use ($user) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $payment = MembershipBankPayment::where('user_id', $user->id)->whereIn('status', ['awaiting_payment', 'under_review', 'rejected'])->first();
            if ($payment) {
                return $payment;
            }
            $previous = MembershipBankPayment::where('user_id', $user->id)->where('status', 'approved')->latest('id')->first();
            if ($user->hasActiveMembership() && $previous) {
                return $previous;
            }
            if ($user->hasActiveMembership()) {
                throw ValidationException::withMessages(['payment' => 'Tu membresía ya está activa.']);
            }
            $payment = MembershipBankPayment::create(['user_id' => $user->id, 'reference' => (string) Str::uuid(),
                'checkout_event_id' => (string) Str::uuid(), 'checkout_at' => now(), 'status' => 'awaiting_payment', 'amount' => '100.00']);
            // Durable canonical event, even if an advertising provider is not configured.
            $context = app(GrowthMeasurementService::class)->context($user->id);
            unset($context['_default_scope']);
            $event = AnalyticsEvent::create([...$context, 'event_id' => $payment->checkout_event_id,
                'event_name' => 'initiate_checkout', 'user_id' => $user->id, 'source_type' => MembershipBankPayment::class,
                'source_id' => $payment->id, 'occurred_at' => $payment->checkout_at,
                'metadata' => ['value' => 100, 'currency' => 'BOB', 'payment_reference' => $payment->reference]]);
            app(MetaDeliveryOutbox::class)->enqueueNew($event);

            return $payment;
        });
    }

    public function approve(int $id, int $movementId, User $admin, bool $strongOnly = false): MembershipBankPayment
    {
        abort_unless($admin->is_admin, 403);

        return DB::transaction(function () use ($id, $movementId, $admin, $strongOnly) {
            // Serialize every decision, including across two requests by the same buyer.
            $owner = MembershipBankPayment::findOrFail($id)->user_id;
            $user = User::lockForUpdate()->findOrFail($owner);
            $payment = MembershipBankPayment::lockForUpdate()->findOrFail($id);
            if ($payment->status === 'approved') {
                abort_unless($payment->bank_movement_id === $movementId, 422, 'La aprobación ya tiene otro movimiento.');

                return $payment;
            }
            abort_unless($payment->status === 'under_review', 422, 'El pago no está en revisión.');
            $movement = DB::table('membership_bank_movements')->where('id', $movementId)->lockForUpdate()->first();
            abort_unless($movement && $movement->amount === '100.00', 422, 'El ingreso debe ser exactamente Bs 100.');
            abort_if(MembershipBankPayment::where('bank_movement_id', $movementId)->exists(), 422, 'Movimiento ya utilizado.');
            if ($strongOnly) {
                $match = $this->match($payment);
                abort_unless($match['classification'] === 'strong_unique' && $match['candidates'][0]->id === $movementId, 422, 'La coincidencia dejó de ser fuerte y única.');
            }
            abort_unless($user->global_subject_id, 422, 'Falta la identidad global del comprador.');
            abort_if($user->hasActiveMembership(), 422, 'El comprador ya tiene membresía activa.');
            // Existing operational activation service; no conversion/reward recorder.
            $membership = app(MembershipService::class)->activate($user, $admin, null, 'bank_qr', $payment->reference, null, '100.00');
            $payment->update(['status' => 'approved', 'bank_movement_id' => $movementId, 'membership_id' => $membership->id,
                'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'activated_at' => $membership->starts_at]);
            $eventId = 'com:membership:'.$payment->reference.':activated';
            $body = ['schema_version' => 1, 'event_id' => $eventId, 'event_type' => 'membership.activated',
                'global_subject_id' => $user->global_subject_id, 'membership_reference' => (string) $membership->id,
                'com_reference' => ['type' => 'membership', 'id' => (string) $membership->id],
                'payment_reference' => $payment->reference, 'occurred_at' => $membership->starts_at->toRfc3339String()];
            DB::table('commercial_outbox')->insertOrIgnore(['event_id' => $eventId, 'raw_body' => json_encode($body, JSON_THROW_ON_ERROR),
                'status' => 'PENDING', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $user->notify(new MembershipBankActivated($payment->reference));
            $this->audit($payment, $admin, 'membership_bank_approved', ['movement_id' => $movementId, 'membership_id' => $membership->id]);

            return $payment->refresh();
        });
    }

    public function match(MembershipBankPayment $payment): array
    {
        $available = DB::table('membership_bank_movements')->where('amount', '100.00')
            ->whereNotIn('id', MembershipBankPayment::whereNotNull('bank_movement_id')->select('bank_movement_id'));
        $exact = $payment->declared_reference ? (clone $available)->where('reference', $payment->declared_reference)->orderBy('id')->get() : collect();
        if ($exact->isNotEmpty()) {
            $claims = MembershipBankPayment::where('status', 'under_review')->where('declared_reference', $payment->declared_reference)->count();

            return ['classification' => $exact->count() === 1 && $claims === 1 ? 'strong_unique' : 'ambiguous', 'candidates' => $exact->all()];
        }
        // A different explicit reference is a conflict, never a time-based match.
        $near = $payment->paid_date ? $available->where('paid_date', $payment->paid_date->format('Y-m-d'))
            ->when($payment->declared_reference, fn ($q) => $q->whereNull('reference'))->orderBy('paid_time')->limit(100)->get() : collect();

        return ['classification' => $near->isEmpty() ? 'no_match' : 'ambiguous', 'candidates' => $near->all()];
    }

    public function audit(MembershipBankPayment $payment, User $actor, string $action, array $metadata = []): void
    {
        AuditLog::create(['actor_user_id' => $actor->id, 'action' => $action, 'subject_type' => MembershipBankPayment::class, 'subject_id' => $payment->id, 'metadata' => $metadata]);
    }
}
