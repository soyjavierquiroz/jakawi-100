<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/index', ['stats' => [
            'partners' => Partner::count(), 'locations' => Location::count(),
            'benefits' => Benefit::count(), 'experiences' => Experience::count(),
            'memberships' => Membership::active()->count(),
            'redemptions' => Redemption::where('status', Redemption::STATUS_CONFIRMED)->count(),
        ]]);
    }

    public function partners(): Response
    {
        return Inertia::render('admin/resources/index', ['title' => 'Partners', 'resource' => 'partners', 'items' => Partner::orderBy('name')->get()]);
    }

    public function partnerForm(?Partner $partner = null): Response
    {
        return Inertia::render('admin/resources/form', ['title' => $partner ? 'Editar Partner' : 'Nuevo Partner', 'resource' => 'partners', 'item' => $partner]);
    }

    public function savePartner(Request $request, ?Partner $partner = null)
    {
        $data = $request->validate($this->partnerRules($partner));
        $partner ??= new Partner;
        $partner->fill($data);
        $partner->save();
        $this->upload($request, $partner, 'logo', 'logo_path', 'partners');
        $this->upload($request, $partner, 'cover', 'cover_path', 'partners');

        return to_route('admin.partners.index');
    }

    public function locations(): Response
    {
        return Inertia::render('admin/resources/index', ['title' => 'Locations', 'resource' => 'locations', 'items' => Location::with('partner')->orderBy('name')->get(), 'partners' => Partner::orderBy('name')->get(['id', 'name'])]);
    }

    public function locationForm(?Location $location = null): Response
    {
        return Inertia::render('admin/resources/form', ['title' => $location ? 'Editar Location' : 'Nueva Location', 'resource' => 'locations', 'item' => $location ? $location->only(array_merge($location->getFillable(), ['id'])) + ['has_redemption_pin' => $location->hasRedemptionPin()] : null, 'partners' => Partner::orderBy('name')->get(['id', 'name'])]);
    }

    public function saveLocation(Request $request, ?Location $location = null)
    {
        $data = $request->validate($this->locationRules($location));
        $location ??= new Location;
        $location->fill($data);
        if (filled($data['redemption_pin'] ?? null)) {
            $location->setRedemptionPin($data['redemption_pin']);
        } $location->save();
        $this->upload($request, $location, 'image', 'image_path', 'locations');

        return to_route('admin.locations.index');
    }

    public function benefits(): Response
    {
        return Inertia::render('admin/resources/index', ['title' => 'Beneficios', 'resource' => 'benefits', 'items' => Benefit::with('partner')->orderBy('title')->get()]);
    }

    public function benefitForm(?Benefit $benefit = null): Response
    {
        return Inertia::render('admin/resources/form', ['title' => $benefit ? 'Editar beneficio' : 'Nuevo beneficio', 'resource' => 'benefits', 'item' => $benefit, 'partners' => Partner::orderBy('name')->get(['id', 'name']), 'locations' => $benefit?->partner?->locations()->get(['id', 'name']) ?? []]);
    }

    public function saveBenefit(Request $request, ?Benefit $benefit = null)
    {
        $validator = Validator::make($request->all(), $this->benefitRules($benefit));
        $validator->after(function ($validator) use ($request) {
            if ($request->input('location_scope') !== 'selected' || ! $request->filled('location_ids') || ! $request->filled('partner_id')) {
                return;
            } $locationIds = $request->input('location_ids', []);
            if (Location::query()->whereIn('id', $locationIds)->where('partner_id', $request->input('partner_id'))->count() !== count(array_unique($locationIds))) {
                $validator->errors()->add('location_ids', 'Selected locations must belong to the benefit partner.');
            }
        });
        $data = $validator->validate();
        $benefit ??= new Benefit;
        $benefit->fill($data);
        $benefit->applies_to_all_locations = $data['location_scope'] === 'all';
        $benefit->save();
        if ($data['location_scope'] === 'all') {
            $benefit->applyToAllLocations();
        } else {
            $benefit->syncLocations($data['location_ids'] ?? []);
        } $this->upload($request, $benefit, 'image', 'image_path', 'benefits');

        return to_route('admin.benefits.index');
    }

    public function experiences(): Response
    {
        return Inertia::render('admin/resources/index', ['title' => 'Experiencias', 'resource' => 'experiences', 'items' => Experience::orderBy('title')->get()]);
    }

    public function experienceForm(?Experience $experience = null): Response
    {
        return Inertia::render('admin/resources/form', ['title' => $experience ? 'Editar experiencia' : 'Nueva experiencia', 'resource' => 'experiences', 'item' => $experience?->load('partners', 'sessions'), 'partners' => Partner::orderBy('name')->get(['id', 'name']), 'locations' => Location::orderBy('name')->get(['id', 'name'])]);
    }

    public function saveExperience(Request $request, ?Experience $experience = null)
    {
        $data = $request->validate($this->experienceRules($experience));
        $experience ??= new Experience;
        $experience->fill($data);
        $experience->save();
        if ($request->has('partners')) {
            $experience->syncPartnersWithRoles($data['partners'] ?? []);
        } $this->upload($request, $experience, 'image', 'image_path', 'experiences');
        $this->upload($request, $experience, 'cover', 'cover_path', 'experiences');

        return to_route('admin.experiences.index');
    }

    public function sessions(Request $request, Experience $experience)
    {
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'nullable|date', 'location_id' => 'nullable|exists:locations,id', 'capacity' => 'nullable|integer|min:1', 'status' => 'required|in:scheduled,cancelled', 'venue_label' => 'nullable|string']);
        $experience->sessions()->create($data);

        return back();
    }

    public function updateSession(Request $request, Experience $experience, ExperienceSession $session)
    {
        abort_unless($session->experience_id === $experience->id, 404);
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'location_id' => 'nullable|exists:locations,id', 'capacity' => 'nullable|integer|min:1', 'status' => 'required|in:scheduled,cancelled', 'venue_label' => 'nullable|string']);
        $session->update($data);

        return back();
    }

    public function deleteSession(Experience $experience, ExperienceSession $session)
    {
        abort_unless($session->experience_id === $experience->id, 404);
        $session->delete();

        return back();
    }

    public function memberships(): Response
    {
        return Inertia::render('admin/memberships/index', ['users' => User::with('activeMembership')->paginate(25), 'membershipConfig' => config('jakawi.membership'), 'paymentMethods' => ['cash', 'transfer', 'other']]);
    }

    public function activateMembership(Request $request, MembershipService $service)
    {
        $d = $request->validate(['user_id' => 'required|exists:users,id', 'starts_at' => 'nullable|date', 'amount_paid' => 'nullable|numeric', 'payment_method' => 'nullable|string', 'payment_reference' => 'nullable|string', 'notes' => 'nullable|string']);
        $service->activate(User::findOrFail($d['user_id']), $request->user(), isset($d['starts_at']) ? now()->parse($d['starts_at']) : null, $d['payment_method'] ?? null, $d['payment_reference'] ?? null, $d['notes'] ?? null, $d['amount_paid'] ?? null);

        return back();
    }

    public function cancelMembership(Membership $membership, MembershipService $service)
    {
        $service->cancel($membership);

        return back();
    }

    public function redemptions(): Response
    {
        return Inertia::render('admin/redemptions/index', ['redemptions' => Redemption::with('user')->latest()->paginate(50)]);
    }

    private function upload(Request $request, object $model, string $input, string $attribute, string $folder): void
    {
        if (! $request->hasFile($input)) {
            return;
        } $old = $model->{$attribute};
        $path = $request->file($input)->store($folder.'/'.$model->id, 'public');
        $model->{$attribute} = $path;
        $model->save();
        if ($old && str_starts_with($old, $folder.'/'.$model->id.'/')) {
            Storage::disk('public')->delete($old);
        }
    }

    private function partnerRules(?Partner $p): array
    {
        return array_merge($this->base('partners', $p), ['name' => 'required|string|max:255', 'entity_type' => 'required|in:organization,individual', 'partner_type' => 'required|in:business,professional,creator,organizer,brand,other', 'legal_name' => 'nullable|string|max:255', 'tax_id' => 'nullable|string|max:255', 'description' => 'nullable|string', 'category' => 'nullable|in:food,cafe,fitness,wellness,beauty,entertainment,nightlife,shopping,services,experiences', 'website' => 'nullable|url', 'instagram' => 'nullable|string', 'facebook' => 'nullable|string', 'tiktok' => 'nullable|string', 'phone' => 'nullable|string', 'whatsapp' => 'nullable|string', 'email' => 'nullable|email', 'contact_name' => 'nullable|string', 'contact_phone' => 'nullable|string', 'contact_email' => 'nullable|email', 'featured' => 'boolean', 'internal_notes' => 'nullable|string', 'sort_order' => 'nullable|integer', 'published_at' => 'nullable|date', 'logo' => 'nullable|image|mimes:jpeg,png,webp|max:5120', 'cover' => 'nullable|image|mimes:jpeg,png,webp|max:5120']);
    }

    private function locationRules(?Location $l): array
    {
        return array_merge($this->base('locations', $l), ['partner_id' => 'nullable|exists:partners,id', 'name' => 'required|string|max:255', 'location_type' => 'required|in:branch,venue,meeting_point,online,mobile,other', 'is_primary' => 'boolean', 'country_code' => 'nullable|string|size:2', 'region' => 'nullable|string', 'city' => 'nullable|string', 'zone' => 'nullable|string', 'address' => 'nullable|string', 'address_reference' => 'nullable|string', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180', 'maps_url' => 'nullable|url', 'google_place_id' => 'nullable|string', 'phone' => 'nullable|string', 'whatsapp' => 'nullable|string', 'email' => 'nullable|email', 'website' => 'nullable|url', 'instagram' => 'nullable|string', 'facebook' => 'nullable|string', 'tiktok' => 'nullable|string', 'timezone' => 'nullable|string', 'opening_hours' => 'nullable|array', 'manager_name' => 'nullable|string', 'manager_phone' => 'nullable|string', 'manager_email' => 'nullable|email', 'redemption_pin' => 'nullable|digits:6', 'sort_order' => 'nullable|integer', 'published_at' => 'nullable|date', 'image' => 'nullable|image|mimes:jpeg,png,webp|max:5120']);
    }

    private function benefitRules(?Benefit $b): array
    {
        return array_merge($this->base('benefits', $b), ['partner_id' => 'required|exists:partners,id', 'title' => 'required|string|max:255', 'short_description' => 'nullable|string', 'description' => 'nullable|string', 'terms' => 'nullable|string', 'category' => 'nullable|in:food,cafe,fitness,wellness,beauty,entertainment,nightlife,shopping,services,experiences', 'benefit_type' => 'nullable|in:percentage,fixed_amount,two_for_one,free_item,upgrade,exclusive_access,other', 'estimated_savings' => 'nullable|numeric|min:0', 'redemption_limit_per_member' => 'nullable|integer|min:1', 'featured' => 'boolean', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'location_scope' => 'required|in:all,selected', 'location_ids' => 'array', 'location_ids.*' => 'integer', 'sort_order' => 'nullable|integer', 'published_at' => 'nullable|date', 'image' => 'nullable|image|mimes:jpeg,png,webp|max:5120']);
    }

    private function experienceRules(?Experience $e): array
    {
        return array_merge($this->base('experiences', $e), ['title' => 'required|string|max:255', 'short_description' => 'nullable|string', 'description' => 'nullable|string', 'terms' => 'nullable|string', 'category' => 'nullable|in:food,cafe,fitness,wellness,beauty,entertainment,nightlife,shopping,services,experiences', 'experience_type' => 'nullable|in:event,workshop,class,tour,tasting,wellness,outdoor,cultural,social,other', 'duration_minutes' => 'nullable|integer|min:1', 'regular_price' => 'nullable|numeric|min:0', 'member_price' => 'nullable|numeric|min:0', 'currency' => 'nullable|string|size:3', 'reservation_method' => 'required|in:whatsapp,url,phone,external,none', 'reservation_url' => 'nullable|url', 'reservation_whatsapp' => 'nullable|string', 'reservation_phone' => 'nullable|string', 'featured' => 'boolean', 'sort_order' => 'nullable|integer', 'published_at' => 'nullable|date', 'partners' => 'array', 'partners.*.partner_id' => 'required|exists:partners,id', 'partners.*.role' => 'required|in:organizer,host,venue,sponsor,participant,creator,provider,other', 'partners.*.sort_order' => 'nullable|integer', 'image' => 'nullable|image|mimes:jpeg,png,webp|max:5120', 'cover' => 'nullable|image|mimes:jpeg,png,webp|max:5120']);
    }

    private function base(string $table, ?object $model): array
    {
        return ['slug' => 'required|string|max:255|unique:'.$table.',slug,'.($model?->id ?? 'NULL'), 'status' => 'required|in:draft,published,paused,archived'];
    }
}
