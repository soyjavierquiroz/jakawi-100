<?php

namespace App\Services\Meta;

use App\Models\{AnalyticsEvent, Benefit, Challenge, Experience, Unlock};

class MetaEventPayloadFactory
{
    public function __construct(private readonly MetaEventMapper $mapper) {}

    public function customData(AnalyticsEvent $event): array
    {
        $category = match ($event->subject_type) {
            Benefit::class => 'benefit', Experience::class => 'experience',
            Challenge::class => 'challenge', Unlock::class => 'unlock', default => null,
        };
        $data = [];
        if ($category && $event->subject_id) {
            $data = ['content_category' => $category, 'content_ids' => [$category.':'.$event->subject_id]];
        }
        if ($event->event_name === 'landing_cta_click') {
            foreach (['cta_kind' => ['signup', 'membership', 'product_detail', 'redeem', 'reserve', 'participate', 'commit', 'external', 'other'],
                'cta_location' => ['hero', 'body', 'final', 'other']] as $key => $allowed) {
                $value = $event->metadata[$key] ?? null;
                if (in_array($value, $allowed, true)) $data[$key] = $value;
            }
        }
        return $data;
    }

    public function browser(AnalyticsEvent $event): ?array
    {
        $mapping = $this->mapper->map($event->event_name);
        return $mapping ? ['event_id' => $event->event_id, 'event_name' => $mapping['name'],
            'standard' => $mapping['standard'], 'custom_data' => (object) $this->customData($event)] : null;
    }

    public function server(AnalyticsEvent $event): array
    {
        $mapping = $this->mapper->map($event->event_name);
        if (! $mapping) throw new \InvalidArgumentException('Unmapped canonical event.');
        $identity = $event->visitor_id ? 'visitor:'.$event->visitor_id : ($event->user_id ? 'user:'.$event->user_id : null);
        if (! $identity) throw new \InvalidArgumentException('Missing first-party identity.');
        $data = ['event_name' => $mapping['name'], 'event_time' => $event->occurred_at->getTimestamp(),
            'event_id' => $event->event_id, 'action_source' => 'website',
            'user_data' => ['external_id' => [hash('sha256', 'jakawi:meta:v1:'.$identity)]]];
        // Only known route categories / validated landing slugs. Never request query, host or domain data.
        $base = rtrim(config('app.url'), '/');
        if (filter_var($base, FILTER_VALIDATE_URL) && in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)
            && ! parse_url($base, PHP_URL_QUERY) && ! parse_url($base, PHP_URL_FRAGMENT) && ! parse_url($base, PHP_URL_USER)) {
            $path = match ($event->event_name) {
                'signup_completed' => '/register',
                'membership_purchase_requested', 'membership_activated' => '/membresia',
                'benefit_redeemed' => '/beneficios', 'experience_reserved' => '/experiencias',
                'challenge_joined' => '/retos', 'unlock_committed' => '/desbloqueos', default => '/',
            };
            if (in_array($event->event_name, ['landing_view', 'landing_cta_click'], true)
                && ! str_starts_with((string) $event->source_route, 'public-journeys.')
                && ! in_array($event->source_route, ['public-landings.cta', 'partners.index', 'programs.show'], true)
                && is_string($event->landing_slug) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $event->landing_slug)) {
                $path = '/l/'.$event->landing_slug;
            }
            $data['event_source_url'] = $base.$path;
        }
        $custom = $this->customData($event);
        if ($custom) $data['custom_data'] = $custom;
        return $data;
    }
}
