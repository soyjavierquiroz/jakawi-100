<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Services\AnalyticsTracker;
use App\Services\HomePersonalizationService;
use App\Services\MemberAffinityService;
use App\Services\MediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home(Request $request, AnalyticsTracker $analytics, HomePersonalizationService $personalization, MemberAffinityService $affinity): Response
    {
        $analytics->homeViewed();
        $membership = $request->user()?->activeMembership()->first();
        $interests = $personalization->interestsFor($request->user());
        $behavior = $affinity->behavioralCategoryAffinity($request->user());
        $affinities = $affinity->combinedCategoryAffinity($request->user(), $behavior);
        $hasGenericExperiencesInterest = in_array('experiences', $interests, true);
        $isPersonalizedHome = $affinities !== [] || $hasGenericExperiencesInterest;
        $benefits = Benefit::available()->with('partner')->orderByDesc('featured')->orderBy('sort_order')->get();
        $experiences = Experience::upcoming()->with(['sessions' => fn ($query) => $query->upcoming()->with('location')])
            ->orderByDesc('featured')->orderBy('sort_order')->get();

        if ($isPersonalizedHome) {
            $benefits = $personalization->rankBenefits($benefits, $affinities);
            $experiences = $personalization->rankExperiences($experiences, $affinities, $hasGenericExperiencesInterest);
        }

        return Inertia::render('welcome', [
            'featuredBenefits' => $benefits->take(3)->map(fn (Benefit $b) => $this->benefitData($b)),
            'featuredExperiences' => $experiences->take(3)->map(fn (Experience $e) => $this->experienceData($e)),
            'membershipSummary' => $membership ? $this->membership($membership) : null,
            'isPersonalizedHome' => $isPersonalizedHome,
            'personalizationSubtitle' => $behavior !== [] ? ($interests !== [] ? 'Según tus intereses y actividad.' : 'Según tu actividad.') : ($interests !== [] ? 'Según tus intereses.' : null),
        ]);
    }

    public function partners(Partner $partner, AnalyticsTracker $analytics): Response
    {
        abort_unless($partner->isPublished(), 404);
        $analytics->partnerViewed($partner);

        return Inertia::render('partners/show', ['partner' => $this->partner($partner), 'locations' => $partner->locations()->published()->get()->map(fn ($x) => $this->locationData($x)), 'benefits' => $partner->benefits()->available()->get()->map(fn ($x) => $this->benefitData($x)), 'experiences' => $partner->experiences()->upcoming()->with(['sessions' => fn ($query) => $query->upcoming()->with('location')])->get()->map(fn ($x) => $this->experienceData($x))]);
    }

    public function location(Location $location, AnalyticsTracker $analytics): Response
    {
        abort_unless($location->isPublished(), 404);
        $analytics->locationViewed($location);

        return Inertia::render('locations/show', ['location' => $this->locationData($location->load('partner')), 'benefits' => $location->benefits()->available()->get()->filter(fn ($b) => $b->isAvailableAt($location))->map(fn ($b) => $this->benefitData($b)), 'sessions' => $location->hasMany(ExperienceSession::class)->upcoming()->with('experience')->get()]);
    }

    public function benefits(Request $request): Response
    {
        $query = Benefit::available()->with('partner')->orderByDesc('featured')->orderBy('sort_order')->orderBy('title');
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return Inertia::render('benefits/index', ['benefits' => $query->get()->map(fn ($b) => $this->benefitData($b)), 'category' => $request->query('category')]);
    }

    public function explore(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));
        $category = $request->query('category');
        $type = $request->query('type');
        $benefits = Benefit::available()->with('partner')->orderByDesc('featured')->orderBy('sort_order');
        $experiences = Experience::upcoming()->with('sessions.location')->orderByDesc('featured')->orderBy('sort_order');
        $partners = Partner::query()->published()->orderByDesc('featured')->orderBy('name');

        if ($category && $category !== 'todos') {
            $benefits->where('category', $category);
            $experiences->where('category', $category);
            $partners->where('category', $category);
        }
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $benefits->where(fn ($query) => $query->where('title', 'ilike', $like)->orWhereHas('partner', fn ($p) => $p->where('name', 'ilike', $like)));
            $experiences->where(fn ($query) => $query->where('title', 'ilike', $like)->orWhereHas('partners', fn ($p) => $p->where('name', 'ilike', $like)));
            $partners->where(fn ($query) => $query->where('name', 'ilike', $like)->orWhere('description', 'ilike', $like));
        }

        $benefitResults = $type === 'experiences' ? collect() : $benefits->get()->map(fn ($b) => $this->benefitData($b) + ['result_type' => 'benefit']);
        $experienceResults = $type === 'benefits' ? collect() : $experiences->get()->map(fn ($e) => $this->experienceData($e) + ['result_type' => 'experience']);
        $partnerResults = $type ? collect() : $partners->get()->map(fn ($p) => $this->partner($p) + ['result_type' => 'partner']);

        return Inertia::render('explore', [
            'query' => $term,
            'category' => $category,
            'type' => $type,
            'benefits' => $benefitResults,
            'experiences' => $experienceResults,
            'partners' => $partnerResults,
            'results' => $partnerResults->concat($benefitResults)->concat($experienceResults)->values(),
        ]);
    }

    public function benefit(Benefit $benefit, Request $request, AnalyticsTracker $analytics): Response
    {
        $benefit->load('partner');
        abort_unless($benefit->isAvailable(), 404);
        $analytics->benefitViewed($benefit);
        $locations = $benefit->availableLocations()->get();

        return Inertia::render('benefits/show', ['benefit' => $this->benefitData($benefit), 'locations' => $locations->map(fn (Location $location) => $this->locationData($location->load('partner'))), 'hasActiveMembership' => $request->user()?->activeMembership()->exists() ?? false]);
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
        abort_unless($experience->isPublished(), 404);
        $analytics->experienceViewed($experience);
        $experience->load([
            'partners',
            'sessions' => fn ($query) => $query->upcoming()->with('location'),
        ]);
        $reservations = $request->user() ? $request->user()->experienceReservations()->where('experience_id', $experience->id)->get()->keyBy('experience_session_id') : collect();

        return Inertia::render('experiences/show', ['experience' => $this->experienceData($experience, true, $reservations), 'hasActiveMembership' => $request->user()?->hasActiveMembership() ?? false]);
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
            'phone' => filled($experience->reservation_phone) ? 'tel:'.$experience->reservation_phone : null,
        };
        abort_unless(filled($destination), 404);
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
        return $b->only(['id', 'slug', 'title', 'short_description', 'description', 'terms', 'category', 'benefit_type', 'estimated_savings', 'redemption_limit_per_member', 'featured', 'starts_at', 'ends_at']) + ['partner' => $b->relationLoaded('partner') ? $this->partner($b->partner) : null] + $this->image($b->image_path, 'benefit_card') + $this->image($b->image_path, 'hero', 'hero');
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
        usort($targets, fn (array $a, array $b) => ($a['method'] === $e->reservation_method ? 0 : 1) <=> ($b['method'] === $e->reservation_method ? 0 : 1));

        return $e->only(['id', 'slug', 'title', 'short_description', 'description', 'terms', 'category', 'experience_type', 'duration_minutes', 'regular_price', 'member_price', 'currency', 'reservation_method', 'featured']) + $this->image($e->image_path, 'experience_card') + $this->image($e->image_path, 'hero', 'hero') + $this->image($e->cover_path, 'hero', 'cover') + ['partners' => $detail ? $e->partners->map(fn ($p) => $this->partner($p) + ['role' => $p->pivot->role]) : [], 'reservation_targets' => $detail ? $targets : [], 'sessions' => $e->relationLoaded('sessions') ? $e->sessions->map(fn ($s) => $s->only(['id', 'starts_at', 'ends_at', 'venue_label', 'capacity', 'status']) + ['location' => $s->location ? $this->locationData($s->location) : null, 'reservation' => $reservations?->get($s->id)?->only(['public_id', 'status', 'party_size'])]) : []];
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
}
