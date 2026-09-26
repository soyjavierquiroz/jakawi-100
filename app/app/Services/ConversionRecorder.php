<?php
namespace App\Services;
use App\Models\AttributionTouch;
use App\Models\Conversion;
use App\Models\ReferralRelationship;
use App\Models\User;
class ConversionRecorder { public function record(User $user, array $data): Conversion { $key = $data['idempotency_key']; $existing = Conversion::where('idempotency_key', $key)->first(); if ($existing) return $existing; $relationship = ReferralRelationship::where('referred_user_id', $user->id)->where('status', 'active')->latest('attributed_at')->first(); $touch = AttributionTouch::where('user_id', $user->id)->latest('occurred_at')->first(); return Conversion::create([...$data, 'user_id' => $user->id, 'currency' => $data['currency'] ?? 'BOB', 'eligible_amount' => $data['eligible_amount'] ?? $data['gross_amount'], 'status' => $data['status'] ?? 'pending', 'occurred_at' => $data['occurred_at'] ?? now(), 'referral_relationship_id' => $relationship?->id, 'attribution_touch_id' => $touch?->id, 'attribution_snapshot' => ['referrer_user_id' => $relationship?->referrer_user_id, 'referral_code' => $relationship?->referral_code, 'utm_source' => $touch?->utm_source, 'utm_medium' => $touch?->utm_medium, 'utm_campaign' => $touch?->utm_campaign, 'utm_content' => $touch?->utm_content]]); } }
