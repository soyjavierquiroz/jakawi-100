<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Support\PublicJourneyConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicJourneyLandingController extends Controller
{
    public function show(Request $request, PublicJourneyConfig $journeys, AttributionService $attribution, AnalyticsTracker $analytics): Response
    {
        $key = $request->route('landingKey');
        $definition = is_string($key) ? $journeys->landing($key) : null;
        abort_unless($definition, 404);
        $cta = $journeys->cta($definition);

        $ref = $request->query('ref');
        $referrer = is_string($ref) ? $attribution->findReferrer($ref) : null;
        $touch = $attribution->recordTouch($request, $referrer, $referrer?->referral_code_normalized, null, $definition['campaign_key']);
        $request->session()->push('attribution_touch_ids', $touch->id);
        $analytics->record('landing_view', [], ['landing' => $key, 'campaign_key' => $definition['campaign_key']]);

        return Inertia::render('public-journeys/show', [
            'landing' => [
                'key' => $key, 'path' => $definition['path'], 'title' => $definition['title'],
                'description' => $definition['description'], 'eyebrow' => $definition['eyebrow'] ?? '',
                'hero_image' => $definition['hero_image'] ?? null, 'blocks' => $definition['blocks'],
                'primary_cta' => $cta, 'seo' => $definition['seo'],
            ],
            'canonical' => url($definition['path']),
        ]);
    }

    public function cta(string $key, PublicJourneyConfig $journeys, AnalyticsTracker $analytics): HttpResponse
    {
        $definition = $journeys->landing($key);
        abort_unless($definition, 404);
        $cta = $journeys->cta($definition);
        $metadata = ['landing' => $key, 'campaign_key' => $definition['campaign_key'], 'destination_type' => $cta['type'],
            'cta_kind' => $cta['type'] === 'external' ? 'external' : ($cta['href'] === '/register' ? 'signup' : 'product_detail'),
            'cta_location' => 'other', 'destination' => $cta['href']];
        if ($cta['destination_kind'] ?? null) $metadata['destination_kind'] = $cta['destination_kind'];
        $analytics->record('landing_cta_click', [], $metadata);
        return response()->noContent();
    }
}
