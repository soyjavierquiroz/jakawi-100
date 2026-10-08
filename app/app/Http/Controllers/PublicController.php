<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Unlock;
use App\Discovery\DiscoveryContext;
use App\Discovery\DiscoveryService;
use App\Discovery\OpportunityType;
use App\Services\AnalyticsTracker;
use App\Services\MediaUrl;
use App\Services\SelectedCity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home(Request $request, AnalyticsTracker $analytics, DiscoveryService $discovery, SelectedCity $selectedCity): Response
    {
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
            'challenges' => array_map(fn ($item) => $item->toArray(), $discovery->explore(new DiscoveryContext(city:$city,user:$request->user(),surface:'home'), OpportunityType::CHALLENGE,limit:4)),
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

    public function partners(Partner $partner, AnalyticsTracker $analytics): Response
    {
        abort_unless($partner->isPublished(), 404);
        $analytics->partnerViewed($partner);

        return Inertia::render('partners/show', ['partner' => $this->partner($partner), 'locations' => $partner->locations()->published()->get()->map(fn ($x) => $this->locationData($x)), 'benefits' => $partner->benefits()->available()->publicAccess()->get()->map(fn ($x) => $this->benefitData($x)), 'experiences' => $partner->experiences()->upcoming()->with(['sessions' => fn ($query) => $query->upcoming()->with('location')])->get()->map(fn ($x) => $this->experienceData($x))]);
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
        if ($benefit->access_mode === 'social_challenge_grant') abort_unless($request->user() && \App\Models\ChallengeRewardGrant::where('user_id', $request->user()->id)->where('benefit_id', $benefit->id)->where('status', 'granted')->exists(), 404);
        $analytics->resetJourneyIntent($benefit);
        $benefit->load('partner');
        $locations = $benefit->isAvailable() ? $benefit->availableLocations()->get()->filter->hasRedemptionPin()->values() : collect();
        $availability = $this->benefitAvailability($benefit, $request, $locations->isNotEmpty());
        $hasActiveMembership = $request->user()?->activeMembership()->exists() ?? false;
        if ($availability['available'] && $request->user() && ! $hasActiveMembership) {
            $analytics->journeyMembershipGateViewed($benefit);
        }
        if ($availability['available']) {
            $analytics->benefitViewed($benefit);
        }

        return Inertia::render('benefits/show', ['benefit' => $this->benefitData($benefit), 'locations' => $locations->map(fn (Location $location) => $this->locationData($location->load('partner'))), 'hasActiveMembership' => $hasActiveMembership, 'availability' => $availability]);
    }

    public function experiences(Request $request): Response
    {
        $query = Experience::upcoming()->with('sessions.location')->orderByDesc('featured')->orderBy('sort_order');
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return Inertia::render('experiences/index', ['experiences' => $query->get()->map(fn ($e) => $this->experienceData($e))]);
    }

    public function experience(Request $request, Experience $experience, AnalyticsTracker $analytics): Response
    {
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

        return Inertia::render('experiences/show', ['experience' => $this->experienceData($experience, true, $reservations), 'hasActiveMembership' => $hasActiveMembership, 'restoredSessionId' => app(\App\Services\PublicJourneyContinuation::class)->restoredExperienceSessionId($experience), 'availability' => ['available' => $available, 'reason' => $available ? null : 'ESTA EXPERIENCIA NO ESTÁ DISPONIBLE AHORA']]);
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
        abort_unless($experience->isPublished() && $experience->upcomingSessions()->exists(), 404);
        $method = $request->query('method', $experience->reservation_method);
        abort_unless(in_array($method, ['whatsapp', 'url', 'external', 'phone'], true), 404);
        $destination = match ($method) {
            'whatsapp' => filled($experience->reservation_whatsapp) ? 'https://wa.me/'.preg_replace('/\D+/', '', $experience->reservation_whatsapp) : null,
            'url', 'external' => filled($experience->reservation_url) ? $experience->reservation_url : null,
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
        return $p->only(['id', 'slug', 'name', 'description', 'category', 'website', 'instagram', 'facebook', 'tiktok', 'phone', 'whatsapp', 'email', 'featured']) + $this->image($p->logo_path, 'thumbnail', 'logo') + $this->image($p->cover_path, 'partner_cover', 'cover');
    }

    private function locationData(Location $l): array
    {
        return $l->only(['id', 'slug', 'name', 'location_type', 'city', 'zone', 'address', 'address_reference', 'opening_hours', 'phone', 'whatsapp', 'website', 'instagram', 'facebook', 'tiktok', 'maps_url']) + ['partner' => $l->relationLoaded('partner') && $l->partner ? $this->partner($l->partner) : null] + $this->image($l->image_path, 'partner_cover');
    }

    private function benefitData(Benefit $b): array
    {
        return $b->only(['id', 'slug', 'title', 'short_description', 'description', 'terms', 'category', 'benefit_type', 'estimated_savings', 'redemption_limit_per_member', 'featured', 'starts_at', 'ends_at']) + ['destination_url'=>app(\App\Services\ProductLandingResolver::class)->defaultUrl($b),'partner' => $b->relationLoaded('partner') ? $this->partner($b->partner) : null] + $this->image($b->image_path, 'benefit_card') + $this->image($b->image_path, 'hero', 'hero');
    }

    private function experienceData(Experience $e, bool $detail = false, $reservations = null): array
    {
        $targets = [];
        if (filled($e->reservation_whatsapp)) {
            $targets[] = ['method' => 'whatsapp', 'label' => 'CONTINUAR POR WHATSAPP'];
        }
        if (filled($e->reservation_url)) {
            $targets[] = ['method' => $e->reservation_method === 'external' ? 'external' : 'url', 'label' => 'IR AL SITIO DE RESERVA'];
        }
        if (filled($e->reservation_phone) && preg_match('/^\+?[1-9]\d{6,14}$/', $e->reservation_phone)) {
            $targets[] = ['method' => 'phone', 'label' => 'LLAMAR PARA RESERVAR'];
        }
        usort($targets, fn (array $a, array $b) => ($a['method'] === $e->reservation_method ? 0 : 1) <=> ($b['method'] === $e->reservation_method ? 0 : 1));

        return $e->only(['id', 'slug', 'title', 'short_description', 'description', 'terms', 'category', 'experience_type', 'duration_minutes', 'regular_price', 'member_price', 'currency', 'reservation_method', 'featured']) + $this->image($e->image_path, 'experience_card') + $this->image($e->image_path, 'hero', 'hero') + $this->image($e->cover_path, 'hero', 'cover') + ['destination_url'=>app(\App\Services\ProductLandingResolver::class)->defaultUrl($e),'partners' => $detail ? $e->partners->map(fn ($p) => $this->partner($p) + ['role' => $p->pivot->role]) : [], 'reservation_targets' => $detail ? $targets : [], 'sessions' => $e->relationLoaded('sessions') ? $e->sessions->map(fn ($s) => $s->only(['id', 'starts_at', 'ends_at', 'venue_label', 'capacity', 'status']) + ['location' => $s->location ? $this->locationData($s->location) : null, 'reservation' => $reservations?->get($s->id)?->only(['public_id', 'status', 'party_size'])]) : []];
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
