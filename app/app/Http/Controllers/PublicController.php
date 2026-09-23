<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Services\AnalyticsTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home(Request $request, AnalyticsTracker $analytics): Response
    {
        $analytics->homeViewed();
        $membership = $request->user()?->activeMembership()->first();

        return Inertia::render('welcome', [
            'featuredBenefits' => Benefit::available()->with('partner')->orderByDesc('featured')->orderBy('sort_order')->take(3)->get()->map(fn (Benefit $b) => $this->benefitData($b)),
            'featuredExperiences' => Experience::upcoming()->with('sessions.location')->orderByDesc('featured')->orderBy('sort_order')->take(3)->get()->map(fn (Experience $e) => $this->experienceData($e)),
            'membershipSummary' => $membership ? $this->membership($membership) : null,
        ]);
    }

    public function partners(Partner $partner, AnalyticsTracker $analytics): Response
    {
        abort_unless($partner->isPublished(), 404);
        $analytics->partnerViewed($partner);

        return Inertia::render('partners/show', ['partner' => $this->partner($partner), 'locations' => $partner->locations()->published()->get()->map(fn ($x) => $this->locationData($x)), 'benefits' => $partner->benefits()->available()->get()->map(fn ($x) => $this->benefitData($x)), 'experiences' => $partner->experiences()->published()->with('sessions.location')->get()->map(fn ($x) => $this->experienceData($x))]);
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
        $experience->load('partners', 'sessions.location', 'sessions.reservationPartner');
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

    public function reserve(Experience $experience, AnalyticsTracker $analytics): RedirectResponse
    {
        abort_unless($experience->isPublished() && in_array($experience->reservation_method, config('jakawi.reservation_methods'), true), 404);
        $destination = match ($experience->reservation_method) {
            'whatsapp' => filled($experience->reservation_whatsapp) ? 'https://wa.me/'.preg_replace('/\D+/', '', $experience->reservation_whatsapp) : null, 'url', 'external' => $experience->reservation_url, 'phone' => filled($experience->reservation_phone) ? 'tel:'.$experience->reservation_phone : null, default => null
        };
        abort_unless(filled($destination), 404);
        $analytics->experienceReserveClicked($experience, $experience->reservation_method);

        return redirect()->away($destination);
    }

    private function partner(Partner $p): array
    {
        return $p->only(['id', 'slug', 'name', 'description', 'category', 'website', 'instagram', 'facebook', 'tiktok', 'phone', 'whatsapp', 'email', 'featured']) + ['logo_url' => $p->logo_path ? \Storage::url($p->logo_path) : null, 'cover_url' => $p->cover_path ? \Storage::url($p->cover_path) : null];
    }

    private function locationData(Location $l): array
    {
        return $l->only(['id', 'slug', 'name', 'location_type', 'city', 'zone', 'address', 'address_reference', 'opening_hours', 'phone', 'whatsapp', 'website', 'instagram', 'facebook', 'tiktok', 'maps_url']) + ['partner' => $l->relationLoaded('partner') && $l->partner ? $this->partner($l->partner) : null, 'image_url' => $l->image_path ? \Storage::url($l->image_path) : null];
    }

    private function benefitData(Benefit $b): array
    {
        return $b->only(['id', 'slug', 'title', 'short_description', 'description', 'terms', 'category', 'benefit_type', 'estimated_savings', 'redemption_limit_per_member', 'featured', 'starts_at', 'ends_at']) + ['partner' => $b->relationLoaded('partner') ? $this->partner($b->partner) : null, 'image_url' => $b->image_path ? \Storage::url($b->image_path) : null];
    }

    private function experienceData(Experience $e, bool $detail = false, $reservations = null): array
    {
        return $e->only(['id', 'slug', 'title', 'short_description', 'description', 'terms', 'category', 'experience_type', 'duration_minutes', 'regular_price', 'member_price', 'currency', 'reservation_method', 'featured']) + ['image_url' => $e->image_path ? \Storage::url($e->image_path) : null, 'cover_url' => $e->cover_path ? \Storage::url($e->cover_path) : null, 'partners' => $detail ? $e->partners->map(fn ($p) => $this->partner($p) + ['role' => $p->pivot->role]) : [], 'sessions' => $e->relationLoaded('sessions') ? $e->sessions->map(fn ($s) => $s->only(['id', 'starts_at', 'ends_at', 'venue_label', 'capacity', 'status']) + ['location' => $s->location ? $this->locationData($s->location) : null, 'reservation' => $reservations?->get($s->id)?->only(['public_id', 'status']), 'reservable' => $e->reservation_method === 'jakawi' && $s->isUpcoming() && $s->reservationPartner?->isPublished()]) : []];
    }

    private function membership(Membership $m): array
    {
        return ['amount_paid' => $m->amount_paid, 'ends_at' => $m->ends_at, 'confirmed_savings' => $m->confirmedSavings(), 'remaining_to_payback' => $m->remainingToPayback(), 'has_paid_for_itself' => $m->hasPaidForItself()];
    }
}
