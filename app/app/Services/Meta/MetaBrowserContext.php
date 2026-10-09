<?php

namespace App\Services\Meta;

use App\Enums\AcquisitionProvider;
use App\Models\AnalyticsEvent;
use App\Services\{AcquisitionProviderResolver, GrowthMeasurementService};
use Illuminate\Http\Request;

class MetaBrowserContext
{
    public const EVENTS_ATTRIBUTE = 'meta_browser_canonical_events';

    public function __construct(private readonly MetaProviderConfiguration $configuration,
        private readonly MetaEventMapper $mapper, private readonly MetaEventPayloadFactory $payloads) {}

    public function descriptor(AnalyticsEvent $event): ?array
    {
        if ($event->acquisition_provider !== AcquisitionProvider::META || ! $this->configuration->browserReady()) return null;
        return $this->payloads->browser($event);
    }

    public function forRequest(Request $request): ?array
    {
        if (app(AcquisitionProviderResolver::class)->suppressed($request) || ! $this->configuration->browserReady()) return null;
        $context = app(GrowthMeasurementService::class)->context();
        if ($context['acquisition_provider'] !== AcquisitionProvider::META) return null;
        $events = [];
        foreach ($request->attributes->get(self::EVENTS_ATTRIBUTE, []) as $event) {
            if ($descriptor = $this->descriptor($event)) $events[] = $descriptor;
        }
        return ['provider' => AcquisitionProvider::META->value, 'pixel_id' => config('meta.pixel_id'),
            'events' => $events, 'cta' => $this->mapper->map('landing_cta_click')];
    }
}
