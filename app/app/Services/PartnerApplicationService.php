<?php

namespace App\Services;

use App\Models\AttributionTouch;
use App\Models\City;
use App\Models\PartnerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerApplicationService
{
    /** @return array{0: PartnerApplication, 1: bool} */
    public function record(City $city, Request $request, array $data): array
    {
        $phone = $this->phone($data['contact_phone'] ?? null);
        $email = $this->text($data['contact_email'] ?? null);
        $business = $this->text($data['business_name']);
        $existing = PartnerApplication::query()->where('city_id', $city->id)->where('business_name_normalized', $business)
            ->where('created_at', '>=', now()->subDays(90))
            ->where(fn ($q) => $phone ? $q->where('contact_phone_normalized', $phone) : $q->where('contact_email_normalized', $email))->latest()->first();
        if ($existing) {
            return [$existing, false];
        }
        $user = $request->user();
        $visitor = app(AttributionService::class)->anonymousId($request);
        $touch = AttributionTouch::query()->when($user, fn ($q) => $q->where('user_id', $user->id), fn ($q) => $q->whereNull('user_id')->where('anonymous_id', $visitor))->latest('occurred_at')->first();

        return DB::transaction(function () use ($city, $user, $visitor, $data, $business, $email, $phone, $touch): array {
            $application = PartnerApplication::create([
                'city_id' => $city->id, 'user_id' => $user?->id, 'visitor_id' => $user ? null : $visitor,
                'business_name' => trim($data['business_name']), 'business_name_normalized' => $business,
                'contact_name' => trim($data['contact_name']), 'contact_email' => $data['contact_email'] ?? null, 'contact_email_normalized' => $email,
                'contact_phone' => $data['contact_phone'] ?? null, 'contact_phone_normalized' => $phone,
                'marketing_opt_in' => filter_var($data['marketing_opt_in'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'marketing_opt_in_at' => now(), 'marketing_opt_in_source' => 'partner_application',
                'category' => $data['category'] ?? null, 'message' => $data['message'] ?? null, 'status' => PartnerApplication::SUBMITTED,
                'attribution_touch_id' => $touch?->id, 'attribution_snapshot' => $touch ? $touch->only(['id', 'anonymous_id', 'user_id', 'referral_code', 'referrer_user_id', 'acquisition_partner_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'landing_page', 'occurred_at']) : null,
            ]);
            $ownership = app(OwnershipAssignmentService::class);
            if ($user) {
                $ownership->inheritFromUser($application, $user);
            } else {
                $ownership->autoAssignGuestPartnerApplication($application);
            }

            return [$application, true];
        });
    }

    private function text(?string $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function phone(?string $value): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $value);

        return $value === '' ? null : $value;
    }
}
