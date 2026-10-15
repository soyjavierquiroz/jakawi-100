<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\City;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\User;
use App\Services\AdminCatalog;
use App\Services\ExperiencePublication;
use App\Services\MediaUploadService;
use App\Services\MediaUrl;
use App\Services\MembershipService;
use App\Services\PartnerAcquisitionMetrics;
use App\Services\ReferralCodeService;
use App\Support\ExperienceRules;
use App\Support\PartnerSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function partners(PartnerAcquisitionMetrics $acquisition): Response
    {
        return Inertia::render('admin/resources/index', ['title' => 'Partners', 'resource' => 'partners', 'items' => Partner::with(['locations' => fn ($query) => $query->select('id', 'partner_id', 'name', 'status', 'city', 'city_id', 'is_primary'), 'locations.cityEntity:id,name'])->orderBy('name')->get()->map(fn (Partner $partner) => $partner->toArray() + ['acquisition' => $acquisition->forPartner($partner), 'logoUrl' => app(MediaUrl::class)->url($partner->logo_path, 'partner_logo'), 'gallery_count' => count($partner->gallery_paths ?? []), 'publicUrl' => $partner->isPublished() ? route('partners.public', $partner) : null])]);
    }

    public function partnerForm(?Partner $partner = null): Response
    {
        return Inertia::render('admin/resources/form', ['title' => $partner ? 'Editar Partner' : 'Nuevo Partner', 'resource' => 'partners', 'item' => $partner ? $partner->toArray() + ['galleryUrls' => array_map(fn ($key) => app(MediaUrl::class)->url($key, 'partner_editorial'), $partner->gallery_paths ?? []), 'logoCropUrl' => $partner->logo_path ? route('admin.partners.logo-source', $partner->id, false) : null, 'logoUrl' => app(MediaUrl::class)->url($partner->logo_path, 'partner_logo'), 'coverUrl' => app(MediaUrl::class)->url($partner->cover_path, 'thumbnail')] : null, 'publicUrl' => $partner?->isPublished() ? route('partners.public', $partner) : null, 'locations' => $partner ? $partner->locations()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'status', 'address', 'city']) : []]);
    }

    public function partnerLogoSource(Partner $partner)
    {
        abort_unless($partner->logo_path, 404);
        $disk = app(MediaUploadService::class)->isMediaKey($partner->logo_path) ? config('media.disk') : 'public';

        return Storage::disk($disk)->response($partner->logo_path, 'partner-logo.webp', [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function savePartner(Request $request, ?Partner $partner = null)
    {
        return DB::transaction(function () use ($request, $partner) {
            $data = $request->validate($this->partnerRules($partner));
            $partner ??= new Partner;
            $partner->fill($data);
            $partner->save();
            $this->upload($request, $partner, 'logo', 'logo_path', 'partners');
            $this->upload($request, $partner, 'cover', 'cover_path', 'partners');
            $paths = [];
            for ($slot = 1; $slot <= 3; $slot++) {
                $paths[] = app(MediaUploadService::class)->fromRequest($request, 'gallery_'.$slot, 'partners', $partner->id, 'gallery', ($partner->gallery_paths ?? [])[$slot - 1] ?? null);
            }
            $partner->gallery_paths = array_values(array_filter($paths, fn ($key) => $key !== null)) ?: null;
            $partner->save();

            return to_route('admin.partners.index')->with('success', 'Partner guardado correctamente.');
        });
    }

    public function partnerReferralCode(Request $request, Partner $partner, ReferralCodeService $codes)
    {
        $previous = $partner->referral_code;
        $code = $request->boolean('regenerate') ? $codes->regenerate($partner) : $codes->ensureFor($partner);
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => $previous ? 'partner_referral_code_regenerated' : 'partner_referral_code_generated', 'subject_type' => Partner::class, 'subject_id' => $partner->id, 'metadata' => ['before' => $previous, 'after' => $code]]);

        return back();
    }

    public function locations(): Response
    {
        return Inertia::render('admin/resources/index', ['title' => 'Locations', 'resource' => 'locations', 'items' => Location::with(['partner', 'cityEntity'])->orderBy('name')->get(), 'partners' => Partner::orderBy('name')->get(['id', 'name'])]);
    }

    public function locationForm(Request $request, ?Location $location = null): Response
    {
        return Inertia::render('admin/resources/form', ['title' => $location ? 'Editar Location' : 'Nueva Location', 'resource' => 'locations', 'item' => $location ? $location->only(array_merge($location->getFillable(), ['id'])) + ['has_redemption_pin' => $location->hasRedemptionPin(), 'imageUrl' => app(MediaUrl::class)->url($location->image_path, 'thumbnail')] : null, 'partners' => Partner::orderBy('name')->get(['id', 'name']), 'cities' => City::orderByDesc('priority')->orderBy('name')->get(['id', 'name']), 'initialPartnerId' => ! $location && $request->filled('partner') ? Partner::whereKey($request->integer('partner'))->value('id') : null]);
    }

    public function saveLocation(Request $request, ?Location $location = null)
    {
        return DB::transaction(function () use ($request, $location) {
            $data = $request->validate($this->locationRules($location));
            $city = isset($data['city_id']) ? City::find($data['city_id']) : null;
            $data['city'] = $city?->name;
            $location ??= new Location;
            $location->fill($data);
            if (filled($data['redemption_pin'] ?? null)) {
                $location->setRedemptionPin($data['redemption_pin']);
            } $location->save();
            $this->upload($request, $location, 'image', 'image_path', 'locations');

            return to_route('admin.locations.index')->with('success', 'Location guardada correctamente.');
        });
    }

    public function benefits(Request $request): Response
    {
        return Inertia::render('admin/catalog/index', app(AdminCatalog::class)->listing($request, 'benefits'));
    }

    public function benefitForm(?Benefit $benefit = null): Response
    {
        return Inertia::render('admin/resources/form', ['title' => $benefit ? 'Editar beneficio' : 'Nuevo beneficio', 'resource' => 'benefits', 'item' => $benefit ? $benefit->load('locations')->toArray() + ['imageUrl' => app(MediaUrl::class)->url($benefit->image_path, 'thumbnail')] : null, 'partners' => Partner::orderBy('name')->get(['id', 'name']), 'locations' => Location::orderBy('name')->get(['id', 'name', 'partner_id'])]);
    }

    public function saveBenefit(Request $request, ?Benefit $benefit = null)
    {
        return DB::transaction(function () use ($request, $benefit) {
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
            if ($benefit && isset($data['access_mode']) && $data['access_mode'] !== $benefit->access_mode && Challenge::where('benefit_id', $benefit->id)->exists()) {
                throw ValidationException::withMessages(['access_mode' => 'No se puede cambiar el acceso de un beneficio vinculado a un reto.']);
            }
            $benefit ??= new Benefit;
            $benefit->fill($data);
            $benefit->applies_to_all_locations = $data['location_scope'] === 'all';
            $benefit->save();
            if ($data['location_scope'] === 'all') {
                $benefit->applyToAllLocations();
            } else {
                $benefit->syncLocations($data['location_ids'] ?? []);
            } $this->upload($request, $benefit, 'image', 'image_path', 'benefits');

            return to_route('admin.benefits.index', $request->only(['q', 'status_filter', 'partner_filter', 'page']));
        });
    }

    public function experiences(Request $request): Response
    {
        return Inertia::render('admin/catalog/index', app(AdminCatalog::class)->listing($request, 'experiences'));
    }

    public function experienceForm(?Experience $experience = null): Response
    {
        return Inertia::render('admin/experiences/form', [
            'item' => $experience ? $experience->load('partners', 'sessions.location')->toArray() + [
                'imageUrl' => app(MediaUrl::class)->url($experience->image_path, 'thumbnail'),
                'coverUrl' => app(MediaUrl::class)->url($experience->cover_path, 'thumbnail'),
            ] : null,
            'publication' => $experience ? app(ExperiencePublication::class)->summary($experience) : null,
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('name')->get(['id', 'name']),
            'categories' => config('jakawi.categories'), 'experienceTypes' => config('jakawi.experience_types'),
        ]);
    }

    public function experiencePreview(Request $request, Experience $experience): Response
    {
        return Inertia::render('experiences/show', $this->experiencePreviewProps($request, $experience));
    }

    private function experiencePreviewProps(Request $request, Experience $experience): array
    {
        $props = app(PublicController::class)->experiencePublicProps($experience, $request);
        $props['availability'] = ['available' => false, 'reason' => 'PREVIEW ADMINISTRATIVO · Las acciones operativas están deshabilitadas.'];
        $props['experience']['reservation_targets'] = [];

        return $props;
    }

    public function saveExperience(Request $request, ?Experience $experience = null)
    {
        return DB::transaction(function () use ($request, $experience) {
            if (! $experience && blank($request->input('slug')) && $request->filled('title')) {
                $baseSlug = Str::slug($request->string('title')->toString()) ?: 'experiencia';
                $slug = $baseSlug;
                for ($suffix = 2; Experience::where('slug', $slug)->exists(); $suffix++) {
                    $slug = $baseSlug.'-'.$suffix;
                }
                $request->merge(['slug' => $slug]);
            }
            $request->mergeIfMissing(['currency' => $experience?->currency ?? 'BOB']);
            $data = $request->validate($this->experienceRules($experience), ExperienceRules::messages(), ExperienceRules::attributes());
            if (($data['catalog_visibility'] ?? null) === 'legacy' && (! $experience || $experience->catalog_visibility !== 'legacy')) {
                throw ValidationException::withMessages(['catalog_visibility' => 'Selecciona Oculta, Próximamente u Oferta disponible. El modo anterior solo se conserva para registros existentes.']);
            }
            $seen = [];
            foreach ($data['partners'] ?? [] as $index => $assignment) {
                $key = $assignment['partner_id'].'|'.$assignment['role'];
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(['partners.'.$index.'.partner_id' => 'Este Partner ya tiene esa función en la experiencia.']);
                }
                $seen[$key] = true;
            }
            $experience ??= new Experience(['catalog_visibility' => 'hidden']);
            $experience->fill($data);
            // Associate only on explicit submission; publication checks see the proposed assignment.
            $experience->save();
            if ($request->has('partners')) {
                $experience->syncPartnersWithRoles($data['partners'] ?? []);
            }
            $policy = app(ExperiencePublication::class);
            if (in_array($experience->catalog_visibility, ['upcoming', 'active'], true) && ! $policy->isPublic($experience)) {
                throw ValidationException::withMessages(['catalog_visibility' => 'Para mostrarla, prepara editorialmente la experiencia y asocia un Partner publicado.']);
            }
            if ($experience->catalog_visibility === 'active' && ($blockers = $policy->operationalBlockers($experience)) !== []) {
                throw ValidationException::withMessages(['catalog_visibility' => implode(' ', $blockers)]);
            }
            $this->upload($request, $experience, 'image', 'image_path', 'experiences');
            $this->upload($request, $experience, 'cover', 'cover_path', 'experiences');

            return to_route('admin.experiences.edit', ['experience' => $experience] + $request->only(['q', 'status_filter', 'partner_filter', 'page']))->with('success', 'Experiencia guardada.');
        });
    }

    public function sessions(Request $request, Experience $experience)
    {
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'location_id' => 'nullable|exists:locations,id', 'reservation_partner_id' => 'nullable|exists:partners,id', 'capacity' => 'nullable|integer|min:1', 'status' => 'required|in:scheduled,cancelled', 'venue_label' => 'nullable|string']);
        abort_if(isset($data['reservation_partner_id']) && ! $experience->partners()->whereKey($data['reservation_partner_id'])->exists(), 422);
        $experience->sessions()->create($data);

        return back();
    }

    public function updateSession(Request $request, Experience $experience, ExperienceSession $session)
    {
        abort_unless($session->experience_id === $experience->id, 404);
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'location_id' => 'nullable|exists:locations,id', 'reservation_partner_id' => 'nullable|exists:partners,id', 'capacity' => 'nullable|integer|min:1', 'status' => 'required|in:scheduled,cancelled', 'venue_label' => 'nullable|string']);
        abort_if(isset($data['reservation_partner_id']) && ! $experience->partners()->whereKey($data['reservation_partner_id'])->exists(), 422);
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

    public function attribution(Request $request): Response
    {
        $query = User::query()->with(['attributionTouches.referrer', 'referralRelationships.referrer', 'conversions']);
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('email', 'ilike', '%'.$request->string('q').'%')->orWhere('name', 'ilike', '%'.$request->string('q').'%'));
        }

        return Inertia::render('admin/attribution/index', ['users' => $query->latest()->paginate(25)->withQueryString(), 'windowDays' => (int) (AppSetting::where('key', 'attribution_window_days')->first()?->value['days'] ?? 30), 'filters' => $request->only('q')]);
    }

    public function updateAttributionSettings(Request $request)
    {
        $data = $request->validate(['attribution_window_days' => ['required', 'integer', 'min:1', 'max:3650']]);
        AppSetting::updateOrCreate(['key' => 'attribution_window_days'], ['value' => ['days' => $data['attribution_window_days']]]);
        AuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'attribution_window_updated', 'subject_type' => AppSetting::class, 'metadata' => ['days' => $data['attribution_window_days']]]);

        return back()->with('success', 'Ventana de atribución actualizada.');
    }

    private function upload(Request $request, object $model, string $input, string $attribute, string $folder): void
    {
        $model->{$attribute} = app(MediaUploadService::class)->fromRequest($request, $input, $folder, $model->id, $input === 'logo' ? 'logo' : 'cover', $model->{$attribute});
        $model->save();
    }

    private function partnerRules(?Partner $p): array
    {
        $galleryRules = ['gallery_paths' => 'prohibited', 'gallery_4' => 'prohibited', 'gallery_4_upload' => 'prohibited', 'gallery_4_remove' => 'prohibited'];
        for ($slot = 1; $slot <= 3; $slot++) {
            $galleryRules['gallery_'.$slot] = 'nullable|image|mimes:jpeg,png,webp|max:10240';
            $galleryRules['gallery_'.$slot.'_upload'] = 'nullable|string';
            $galleryRules['gallery_'.$slot.'_remove'] = 'sometimes|boolean';
        }

        return array_merge($this->base('partners', $p), $galleryRules, ['slug' => ['required', 'string', 'max:255', Rule::unique('partners', 'slug')->ignore($p?->id), PartnerSlug::rule()], 'name' => 'required|string|max:255', 'entity_type' => 'required|in:organization,individual', 'partner_type' => 'required|in:business,professional,creator,organizer,brand,other', 'legal_name' => 'nullable|string|max:255', 'tax_id' => 'nullable|string|max:255', 'description' => 'nullable|string', 'category' => 'nullable|in:food,cafe,fitness,wellness,beauty,entertainment,nightlife,shopping,services,experiences', 'website' => 'nullable|url', 'instagram' => 'nullable|string', 'facebook' => 'nullable|string', 'tiktok' => 'nullable|string', 'phone' => 'nullable|string', 'whatsapp' => 'nullable|string', 'email' => 'nullable|email', 'contact_name' => 'nullable|string', 'contact_phone' => 'nullable|string', 'contact_email' => 'nullable|email', 'featured' => 'boolean', 'internal_notes' => 'nullable|string', 'sort_order' => 'nullable|integer', 'published_at' => 'nullable|date', 'logo' => 'nullable|image|mimes:jpeg,png,webp|max:10240', 'cover' => 'nullable|image|mimes:jpeg,png,webp|max:10240']);
    }

    private function locationRules(?Location $l): array
    {
        return array_merge($this->base('locations', $l), ['partner_id' => 'nullable|exists:partners,id', 'city_id' => 'nullable|exists:cities,id|required_unless:location_type,online', 'name' => 'required|string|max:255', 'location_type' => 'required|in:branch,venue,meeting_point,online,mobile,other', 'is_primary' => 'boolean', 'country_code' => 'nullable|string|size:2', 'region' => 'nullable|string', 'zone' => 'nullable|string', 'address' => 'nullable|string', 'address_reference' => 'nullable|string', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180', 'maps_url' => 'nullable|url', 'google_place_id' => 'nullable|string', 'phone' => 'nullable|string', 'whatsapp' => 'nullable|string', 'email' => 'nullable|email', 'website' => 'nullable|url', 'instagram' => 'nullable|string', 'facebook' => 'nullable|string', 'tiktok' => 'nullable|string', 'timezone' => 'nullable|string', 'opening_hours' => 'nullable|array', 'manager_name' => 'nullable|string', 'manager_phone' => 'nullable|string', 'manager_email' => 'nullable|email', 'redemption_pin' => 'nullable|digits:6', 'sort_order' => 'nullable|integer', 'published_at' => 'nullable|date', 'image' => 'nullable|image|mimes:jpeg,png,webp|max:10240']);
    }

    private function benefitRules(?Benefit $b): array
    {
        return array_merge($this->base('benefits', $b), ['partner_id' => 'required|exists:partners,id', 'title' => 'required|string|max:255', 'short_description' => 'nullable|string', 'description' => 'nullable|string', 'terms' => 'nullable|string', 'category' => 'nullable|in:food,cafe,fitness,wellness,beauty,entertainment,nightlife,shopping,services,experiences', 'benefit_type' => 'nullable|in:percentage,fixed_amount,two_for_one,free_item,upgrade,exclusive_access,other', 'access_mode' => ['sometimes', Rule::in(Benefit::ACCESS_MODES)], 'estimated_savings' => 'nullable|numeric|min:0', 'redemption_limit_per_member' => 'nullable|integer|min:1', 'featured' => 'boolean', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'location_scope' => 'required|in:all,selected', 'location_ids' => 'array', 'location_ids.*' => 'integer', 'sort_order' => 'nullable|integer', 'published_at' => 'nullable|date', 'image' => 'nullable|image|mimes:jpeg,png,webp|max:10240']);
    }

    private function experienceRules(?Experience $e): array
    {
        return array_merge($this->base('experiences', $e), ['slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('experiences', 'slug')->ignore($e?->id)], 'title' => 'required|string|max:255', 'short_description' => 'nullable|string', 'description' => 'nullable|string', 'terms' => 'nullable|string', 'category' => 'nullable|in:food,cafe,fitness,wellness,beauty,entertainment,nightlife,shopping,services,experiences', 'experience_type' => 'nullable|in:event,workshop,class,tour,tasting,wellness,outdoor,cultural,social,other', 'duration_minutes' => 'nullable|integer|min:1', 'regular_price' => 'nullable|numeric|min:0', 'member_price' => 'nullable|numeric|min:0', 'currency' => 'nullable|string|size:3', 'reservation_method' => 'required|in:whatsapp,url,phone,external,jakawi,none', 'reservation_url' => 'nullable|url', 'reservation_whatsapp' => 'nullable|string', 'reservation_phone' => 'nullable|string', 'featured' => 'boolean', 'sort_order' => 'nullable|integer', 'published_at' => 'nullable|date', 'partners' => 'array', 'partners.*.partner_id' => 'required|exists:partners,id', 'partners.*.role' => 'required|in:organizer,host,venue,sponsor,participant,creator,provider,other', 'partners.*.sort_order' => 'nullable|integer', 'image' => 'nullable|image|mimes:jpeg,png,webp|max:10240', 'cover' => 'nullable|image|mimes:jpeg,png,webp|max:10240'], ExperienceRules::commercial());
    }

    private function base(string $table, ?object $model): array
    {
        return ['slug' => 'required|string|max:255|unique:'.$table.',slug,'.($model?->id ?? 'NULL'), 'status' => 'required|in:draft,published,paused,archived'];
    }
}
