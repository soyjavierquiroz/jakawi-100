<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsTracker;
use App\Support\PublicJourneyConfig;
use Illuminate\Http\RedirectResponse;

class ExternalRedirectController extends Controller
{
    public function show(string $slug, PublicJourneyConfig $journeys, AnalyticsTracker $analytics): RedirectResponse
    {
        $entry = $journeys->redirectEntry($slug);
        $destination = $entry ? $journeys->destination($entry) : null;
        abort_unless($destination, 404);

        $metadata = ['redirect_slug' => $slug, 'destination_type' => $entry['destination_type']];
        if (isset($entry['campaign_key'])) $metadata['campaign_key'] = $entry['campaign_key'];
        if (isset($entry['landing'])) $metadata['landing'] = $entry['landing'];
        $analytics->record('external_redirect', [], $metadata);

        return redirect()->away($destination);
    }
}
