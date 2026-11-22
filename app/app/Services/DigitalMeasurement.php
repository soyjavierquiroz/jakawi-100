<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\DigitalOrder;
use App\Models\DigitalProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Local canonical events only: no affiliate, CRM or CLUB delivery. */
class DigitalMeasurement
{
    public function record(string $name, Model $source, ?DigitalOrder $order = null, bool $once = true, ?int $userId = null): void
    {
        if (! config('jakawi.analytics.enabled')) {
            return;
        }
        $data = ['event_id' => (string) Str::uuid(), 'event_name' => $name, 'source_type' => $once ? $source->getMorphClass() : null, 'source_id' => $once ? $source->id : null, 'subject_type' => DigitalProduct::class, 'subject_id' => $order?->digital_product_id ?? $source->id, 'user_id' => $order?->user_id ?? $userId, 'occurred_at' => now(), 'metadata' => $order ? ['order_reference' => $order->reference, 'amount' => $order->amount, 'currency' => $order->currency, 'member_at_purchase' => $order->member_at_purchase] : []];
        // Repeated views/downloads use subject identity, leaving outcome uniqueness intact.
        $data['visitor_id'] = (!$order || request()->user()?->id === $order->user_id)
            ? request()->attributes->get(\App\Http\Middleware\EnsureVisitorId::ATTRIBUTE) : null;
        if ($once) {
            AnalyticsEvent::firstOrCreate(['event_name' => $name, 'source_type' => $data['source_type'], 'source_id' => $source->id], $data);
        } else {
            AnalyticsEvent::create($data);
        }
    }
}
