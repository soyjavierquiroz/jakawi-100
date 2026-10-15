<?php

namespace App\Http\Controllers;

use App\Discovery\DiscoveryContext;
use App\Discovery\DiscoveryService;
use App\Discovery\OpportunityType;
use App\Models\Benefit;
use App\Models\ChallengeRewardGrant;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Services\AcquisitionProviderResolver;
use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Services\ExperiencePricing;
use App\Services\ExperiencePublication;
use App\Services\MediaUrl;
use App\Services\ProductLandingResolver;
use App\Services\PublicJourneyContinuation;
use App\Services\SelectedCity;
use App\Support\PartnerSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home(Request $request, AnalyticsTracker $analytics, DiscoveryService $discovery, SelectedCity $selectedCity): Response
    {
        if (config('jakawi.prelaunch_mode')) {
            return Inertia::render('coming-soon', [
                'community' => app(\App\Services\CityCommunity::class)->data($request, 'home'),
            ]);
        }

        $analytics->homeViewed();
        $city = $selectedCity->resolve($request);
        $membership = $request->user()?->activeMembership()->first();
        $result = $discovery->discover(new DiscoveryContext(
            city: $city,
            user: $request->user(),
            surface: 'home',
            forYouLimit: 5,
            happeningNowLimit: 4,
            discoverMoreLimit: 8,
        ));

        return Inertia::render('welcome', [
            'community' => app(\App\Services\CityCommunity::class)->data($request, 'home'),
            'challenges' => array_map(fn ($item) => $item->toArray(), $discovery->explore(new DiscoveryContext(city: $city, user: $request->user(), surface: 'home'), OpportunityType::CHALLENGE, limit: 4)),
            'discovery' => [
                'hero' => $result->hero?->toArray(),
                'forYou' => array_map(fn ($item) => $item->toArray(), $result->forYou),
                'happeningNow' => array_map(fn ($item) => $item->toArray(), $result->happeningNow),
                'discoverMore' => array_map(fn ($item) => $item->toArray(), $result->discoverMore),
            ],
            'myJakawi' => $membership ? $this->membership($membership) : null,
            'categories' => config('jakawi.categories'),
        ]);
    }

    public function partners(Request $request, Partner $partner, AnalyticsTracker $analytics): Response
    {
        abort_unless($partner->isPublished() && PartnerSlug::valid($partner->slug), 404);
        $analytics->partnerViewed($partner);
        if (! $request->user() && ! app(AcquisitionProviderResolver::class)->suppressed($request)) {
            $touch = app(AttributionService::class)->recordTouch($request, $partner, $partner->referral_code_normalized);
            $request->session()->push('attribution_touch_ids', $touch->id);
        }
        $challenges = $partner->challenges()->with('landingPresentations:id,subject_type,subject_id,slug,status,default_scope')->where('review_status', 'APPROVED')->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('published_from')->orWhere('published_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('published_until')->orWhere('published_until', '>=', now()))->get();

        return Inertia::render('partners/show', [
            'canonicalUrl' => rtrim(config('app.url'), '/').'/'.$partner->slug,
            'partner' => $this->partner($partner) + $this->image($partner->cover_path, 'partner_editorial', 'cover_desktop'),
            'locations' => $partner->locations()->published()->with('cityEntity')->orderBy('sort_order')->get()->map(fn ($x) => $this->locationData($x)),
            'benefits' => $partner->benefits()->available()->publicAccess()->with('landingPresentations:id,subject_type,subject_id,slug,status,default_scope')->orderBy('sort_order')->get()->map(fn ($x) => $this->benefitData($x, true)),
            'experiences' => $partner->experiences()->catalogVisible()->with(['landingPresentations:id,subject_type,subject_id,slug,status,default_scope', 'sessions' => fn ($q) => $q->upcoming()->with('location')->orderBy('starts_at')])->get()->unique('id')->values()->map(fn ($x) => $this->experienceData($x)),
            'challenges' => $challenges->map(fn ($x) => $x->only(['id', 'slug', 'title', 'description', 'ends_at', 'reward_type', 'reward_jp_amount', 'manual_prize_description']) + ['destination_url' => app(ProductLandingResolver::class)->navigationUrl($x, (bool) $request->user())] + $this->image($x->hero_path, 'hero')),
        ]);
    }

    public function location(Location $location, AnalyticsTracker $analytics): Response
    {
        abort_unless($location->isPublished(), 404);
        $analytics->locationViewed($location);

        return Inertia::render('locations/show', ['location' => $this->locationData($location->load('partner')), 'benefits' => $location->benefits()->available()->publicAccess()->get()->filter(fn ($b) => $b->isAvailableAt($location))->map(fn ($b) => $this->benefitData($b)), 'sessions' => $location->hasMany(ExperienceSession::class)->upcoming()->with('experience')->get()]);
    }

    public function benefits(Request $request): Response
    {
        $query = Benefit::available()->publicAccess()->with('partner')->orderByDesc('featured')->orderBy('sort_order')->orderBy('title');
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return Inertia::render('benefits/index', ['benefits' => $query->get()->map(fn ($b) => $this->benefitData($b)), 'category' => $request->query('category')]);
    }

    public function explore(Request $request, DiscoveryService $discovery, SelectedCity $selectedCity): Response
    {
        $city = $selectedCity->resolve($request);
        $term = preg_replace('/\s+/', ' ', trim((string) $request->query('q', ''))) ?? '';
        $category = $request->query('category');
        $category = in_array($category, config('jakawi.categories'), true) ? $category : null;
        $type = in_array($request->query('type'), ['benefits', 'experiences', 'challenges', 'unlocks', 'places'], true) ? $request->query('type') : null;
        $partners = Partner::query()->published()->whereHas('locations', fn (Builder $query) => $query->published()->where('city_id', $city->id))->orderByDesc('featured')->orderBy('name');

        if ($category !== null) {
            $partners->where('category', $category);
        }
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $partners->where(fn ($query) => $query->where('name', 'ilike', $like)->orWhere('description', 'ilike', $like));
        }

        $opportunityType = match ($type) {
            'benefits' => OpportunityType::BENEFIT,
            'experiences' => OpportunityType::EXPERIENCE,
            'unlocks' => OpportunityType::UNLOCK,
            'challenges' => OpportunityType::CHALLENGE,
            default => null,
        };
        $opportunities = $type === 'places' ? [] : $discovery->explore(new DiscoveryContext(city: $city, user: $request->user(), surface: 'explore', candidateLimitPerDomain: 24), $opportunityType, $category, $term, 24);
        $places = $type === 'places' ? $partners->limit(24)->get()->map(fn (Partner $partner) => $this->partner($partner))->values() : collect();

        return Inertia::render('explore', [
            'query' => $term,
            'category' => $category,
            'type' => $type,
            'categories' => config('jakawi.categories'),
            'opportunities' => array_map(fn ($item) => $item->toArray(), $opportunities),
            'places' => $places,
        ]);
    }

    public function benefit(Benefit $benefit, Request $request, AnalyticsTracker $analytics): Response
    {
        $props = $this->benefitPublicProps($benefit, $request);
        $analytics->resetJourneyIntent($benefit);
        if ($props['availability']['available'] && $request->user() && $benefit->requiresMembership() && ! $props['hasActiveMembership']) {
            $analytics->journeyMembershipGateViewed($benefit);
        }
        if ($props['availability']['available']) {
            $analytics->benefitViewed($benefit);
        }

        return Inertia::render('benefits/show', $props);
    }

    public function benefitPublicProps(Benefit $benefit, Request $request, bool $preview = false): array
    {
        abort_if(! $preview && $benefit->status === 'draft', 404);

        $grant = null;
        if (! $preview && $benefit->access_mode === 'social_challenge_grant') {
            $grant = $request->user() ? ChallengeRewardGrant::where('user_id', $request->user()->id)->where('benefit_id', $benefit->id)->where('status', 'granted')->first() : null;
            abort_unless($grant, 404);
        }
        $benefit->load('partner');
        $locations = $benefit->isAvailable() ? $benefit->availableLocations()->get()->filter->hasRedemptionPin()->values() : collect();

        return ['rewardUrl' => $grant ? route('social-challenges.show', $grant->participation->challenge) : null, 'benefit' => $this->benefitData($benefit), 'locations' => $locations->map(fn (Location $location) => $this->locationData($location->load('partner'))),
            'hasActiveMembership' => $request->user()?->activeMembership()->exists() ?? false,
            'availability' => $this->benefitAvailability($benefit, $request, $locations->isNotEmpty())];
    }

    public function experiences(Request $request): Response
    {
        $query = Experience::catalogVisible()->with('sessions.location')->orderByDesc('featured')->orderBy('sort_order');
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return Inertia::render('experiences/index', ['experiences' => $query->get()->map(fn ($e) => $this->experienceData($e))]);
    }

    public function experience(Request $request, Experience $experience, AnalyticsTracker $analytics): Response
    {
        abort_unless(app(ExperiencePublication::class)->isPublic($experience), 404);
        $analytics->resetJourneyIntent($experience);
        $available = $experience->isPublished();
        if ($available) {
            $analytics->experienceViewed($experience);
        }
        $experience->load([
            'partners',
            'sessions' => fn ($query) => $available ? $query->upcoming()->with('location') : $query->whereRaw('1 = 0'),
        ]);
        $reservations = $request->user() ? $request->user()->experienceReservations()->where('experience_id', $experience->id)->get()->keyBy('experience_session_id') : collect();
        $hasActiveMembership = $request->user()?->hasActiveMembership() ?? false;
        if ($available && $experience->reservation_method === 'jakawi' && $experience->sessions->isNotEmpty() && $request->user() && ! $hasActiveMembership) {
            $analytics->journeyMembershipGateViewed($experience);
        }

        return Inertia::render('experiences/show', $this->experiencePublicProps($experience, $request));
    }

    public function experiencePublicProps(Experience $experience, Request $request): array
    {
        $available = app(ExperiencePublication::class)->canOperate($experience);
        $showSessions = $experience->isPublished() && ! in_array($experience->catalog_visibility, ['hidden', 'upcoming'], true);
        $experience->load(['partners', 'sessions' => fn ($query) => $showSessions ? $query->upcoming()->with('location') : $query->whereRaw('1 = 0')]);
        $reservations = $request->user() ? $request->user()->experienceReservations()->where('experience_id', $experience->id)->get()->keyBy('experience_session_id') : collect();
        $hasActiveMembership = $request->user()?->hasActiveMembership() ?? false;

        return ['experience' => $this->experienceData($experience, true, $reservations), 'hasActiveMembership' => $hasActiveMembership, 'restoredSessionId' => app(PublicJourneyContinuation::class)->restoredExperienceSessionId($experience), 'availability' => ['available' => $available, 'reason' => $available ? null : ($experience->catalog_visibility === 'upcoming' ? 'EN PREPARACIÓN · Fechas y operación pendientes de confirmación. No admite reservas ni pagos.' : 'ESTA EXPERIENCIA NO ESTÁ DISPONIBLE AHORA')]];
    }

    public function map(Location $location, AnalyticsTracker $analytics): RedirectResponse
    {
        abort_unless($location->isPublished() && filled($location->maps_url), 404);
        $analytics->mapsClicked($location, 'location_detail');

        return redirect()->away($location->maps_url);
    }

    public function locationWhatsapp(Location $location, AnalyticsTracker $analytics): RedirectResponse
    {
        abort_unless($location->isPublished() && filled($location->whatsapp), 404);
        $analytics->whatsappClicked($location);

        return redirect()->away('https://wa.me/'.preg_replace('/\D+/', '', $location->whatsapp));
    }

    public function partnerWhatsapp(Partner $partner, AnalyticsTracker $analytics): RedirectResponse
    {
        abort_unless($partner->isPublished() && filled($partner->whatsapp), 404);
        $analytics->whatsappClicked($partner);

        return redirect()->away('https://wa.me/'.preg_replace('/\D+/', '', $partner->whatsapp));
    }

    public function reserve(Request $request, Experience $experience, AnalyticsTracker $analytics): RedirectResponse
    {
        abort_unless(app(ExperiencePublication::class)->canOperate($experience), 404);
        $method = $request->query('method', $experience->reservation_method);
        abort_unless($experience->catalog_visibility === 'legacy' || $method === $experience->reservation_method, 404);
        abort_unless(in_array($method, ['whatsapp', 'url', 'external', 'phone'], true), 404);
        $destination = match ($method) {
            'whatsapp' => preg_match('/^\+?[1-9]\d{6,14}$/', $experience->reservation_whatsapp ?? '') ? 'https://wa.me/'.preg_replace('/\D+/', '', $experience->reservation_whatsapp) : null,
            'url', 'external' => filter_var($experience->reservation_url, FILTER_VALIDATE_URL) && in_array(parse_url($experience->reservation_url, PHP_URL_SCHEME), ['http', 'https'], true) ? $experience->reservation_url : null,
            'phone' => filled($experience->reservation_phone) && preg_match('/^\+?[1-9]\d{6,14}$/', $experience->reservation_phone) ? 'tel:'.$experience->reservation_phone : null,
        };
        abort_unless(filled($destination), 404);
        $analytics->journeyIntentStarted($experience);
        $analytics->journeyExternalExit($experience, $method);
        $analytics->experienceReserveClicked($experience, $method);
        if ($method === 'whatsapp') {
            $analytics->experienceWhatsappClicked($experience);
        }

        return redirect()->away($destination);
    }

    private function partner(Partner $p): array
    {
        return $p->only(['id', 'slug', 'name', 'description', 'category', 'website', 'instagram', 'facebook', 'tiktok', 'phone', 'whatsapp', 'email', 'featured']) + ['gallery' => array_map(fn ($key) => $this->image($key, 'partner_editorial'), $p->gallery_paths ?? [])] + $this->image($p->logo_path, 'partner_logo', 'logo') + $this->image($p->cover_path, 'partner_cover', 'cover');
    }

    private function locationData(Location $l): array
    {
        return $l->only(['id', 'slug', 'name', 'location_type', 'city', 'zone', 'address', 'address_reference', 'opening_hours', 'phone', 'whatsapp', 'website', 'instagram', 'facebook', 'tiktok', 'maps_url']) + ['city_name' => $l->relationLoaded('cityEntity') ? $l->cityEntity?->name : $l->city, 'partner' => $l->relationLoaded('partner') && $l->partner ? $this->partner($l->partner) : null] + $this->image($l->image_path, 'partner_cover');
    }

    private function benefitData(Benefit $b, bool $inventory = false): array
    {
        return $b->only(['id', 'slug', 'title', 'short_description', 'description', 'terms', 'category', 'benefit_type', 'access_mode', 'estimated_savings', 'redemption_limit_per_member', 'featured', 'starts_at', 'ends_at']) + ['availability_label' => $inventory ? ($b->availableLocations()->exists() ? 'Disponible · revisa las condiciones' : 'Sin ubicación disponible ahora') : null, 'destination_url' => app(ProductLandingResolver::class)->navigationUrl($b, auth()->check()), 'partner' => $b->relationLoaded('partner') ? $this->partner($b->partner) : null] + $this->image($b->image_path, 'benefit_card') + $this->image($b->image_path, 'hero', 'hero');
    }

    private function experienceData(Experience $e, bool $detail = false, $reservations = null): array
    {
        $targets = [];
        if (($e->catalog_visibility === 'legacy' || $e->reservation_method === 'whatsapp') && preg_match('/^\+?[1-9]\d{6,14}$/', $e->reservation_whatsapp ?? '')) {
            $targets[] = ['method' => 'whatsapp', 'label' => 'CONTINUAR POR WHATSAPP'];
        }
        if (($e->catalog_visibility === 'legacy' || in_array($e->reservation_method, ['url', 'external'], true)) && filter_var($e->reservation_url, FILTER_VALIDATE_URL) && in_array(parse_url($e->reservation_url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $targets[] = ['method' => $e->reservation_method === 'external' ? 'external' : 'url', 'label' => 'IR AL SITIO DE RESERVA'];
        }
        if (($e->catalog_visibility === 'legacy' || $e->reservation_method === 'phone') && filled($e->reservation_phone) && preg_match('/^\+?[1-9]\d{6,14}$/', $e->reservation_phone)) {
            $targets[] = ['method' => 'phone', 'label' => 'LLAMAR PARA RESERVAR'];
        }
        usort($targets, fn (array $a, array $b) => ($a['method'] === $e->reservation_method ? 0 : 1) <=> ($b['method'] === $e->reservation_method ? 0 : 1));

        return ['availability_label' => $e->catalog_visibility === 'upcoming' ? 'Próximamente · Sin reservas' : 'Consulta las fechas y condiciones', 'catalog_visibility' => $e->catalog_visibility, 'duration_minutes' => $e->duration_minutes, 'currency' => $e->currency, 'prices' => app(ExperiencePricing::class)->present($e, auth()->user())] + $e->only(['id', 'slug', 'title', 'short_description', 'description', 'terms', 'category', 'experience_type', 'duration_minutes', 'regular_price', 'member_price', 'currency', 'reservation_method', 'featured']) + $this->image($e->image_path ?: $e->cover_path, 'experience_card') + $this->image($e->image_path, 'hero', 'hero') + $this->image($e->cover_path, 'hero', 'cover') + ['destination_url' => app(ProductLandingResolver::class)->navigationUrl($e, auth()->check()), 'partners' => $detail ? $e->partners->map(fn ($p) => $this->partner($p) + ['role' => $p->pivot->role]) : [], 'reservation_targets' => $detail && app(ExperiencePublication::class)->canOperate($e) ? $targets : [], 'sessions' => $e->catalog_visibility !== 'upcoming' && $e->relationLoaded('sessions') ? $e->sessions->map(fn ($s) => $s->only(['id', 'starts_at', 'ends_at', 'venue_label', 'capacity', 'status']) + ['location' => $s->location ? $this->locationData($s->location) : null, 'reservation' => $reservations?->get($s->id)?->only(['public_id', 'status', 'party_size'])]) : []];
    }

    private function image(?string $key, string $preset, string $name = 'image'): array
    {
        $media = app(MediaUrl::class);

        return [$name.'_url' => $media->url($key, $preset), $name.'_srcset' => $media->srcset($key, $preset)];
    }

    private function membership(Membership $m): array
    {
        return ['amount_paid' => $m->amount_paid, 'ends_at' => $m->ends_at, 'confirmed_savings' => $m->confirmedSavings(), 'remaining_to_payback' => $m->remainingToPayback(), 'has_paid_for_itself' => $m->hasPaidForItself()];
    }

    /** @return array{available: bool, reason: string|null} */
    private function benefitAvailability(Benefit $benefit, Request $request, bool $hasUsableLocation): array
    {
        if (! $benefit->isAvailable()) {
            return ['available' => false, 'reason' => 'ESTE BENEFICIO NO ESTÁ DISPONIBLE AHORA'];
        }
        if (! $hasUsableLocation) {
            return ['available' => false, 'reason' => 'ESTE BENEFICIO NO TIENE UNA UBICACIÓN DISPONIBLE AHORA'];
        }
        $limit = $benefit->redemption_limit_per_member;
        if ($request->user() && $limit !== null && $request->user()->redemptions()->where('benefit_id', $benefit->id)->where('status', 'confirmed')->count() >= $limit) {
            return ['available' => false, 'reason' => 'YA USASTE ESTE BENEFICIO SEGÚN SUS CONDICIONES'];
        }

        return ['available' => true, 'reason' => null];
    }
}
