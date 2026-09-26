<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Services\AnalyticsTracker;
use App\Support\ConversionIntent;
use App\Services\MediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversionPaywallController extends Controller
{
    public function show(Request $request, Benefit $benefit, ConversionIntent $intent, AnalyticsTracker $analytics): Response|RedirectResponse
    {
        $benefit->load('partner');
        abort_unless($benefit->isAvailable(), 404);

        if ($request->user()?->hasActiveMembership()) {
            return to_route('benefits.show', $benefit);
        }

        $intent->store($request, $benefit);
        $analytics->benefitUnlockClicked($benefit);
        $analytics->membershipViewed($benefit);

        return Inertia::render('membership/paywall', [
            'paywall' => [
                'triggerBenefit' => [
                    'id' => $benefit->id,
                    'slug' => $benefit->slug,
                    'title' => $benefit->title,
                    'partner' => $benefit->partner ? [
                        'name' => $benefit->partner->name,
                        'slug' => $benefit->partner->slug,
                    ] : null,
                    'estimated_savings' => $benefit->estimated_savings,
                    'image_url' => app(MediaUrl::class)->url($benefit->image_path, 'hero'),
                    'image_srcset' => app(MediaUrl::class)->srcset($benefit->image_path, 'hero'),
                ],
                'membership' => [
                    'price_bob' => config('jakawi.membership.price_bob'),
                    'duration_days' => config('jakawi.membership.duration_days'),
                    'benefits_copy' => ['Beneficios exclusivos', 'Experiencias JAKAWI', 'Ahorro acumulado'],
                ],
                'state' => $request->user() ? ($request->user()->memberships()->exists() ? 'expired' : 'free') : 'guest',
                'can_create_account' => $request->user() === null,
            ],
        ]);
    }
}
