<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PartnerContentController extends Controller
{
    public function benefits(Partner $partner): Response
    {
        return Inertia::render('partner/content/index', ['partner' => $partner->only('name', 'slug'), 'kind' => 'benefit', 'items' => \App\Support\ListingPage::make(Benefit::where('partner_id', $partner->id)->latest())]);
    }

    public function benefitForm(Partner $partner, ?Benefit $benefit = null): Response
    {
        $this->ownedBenefit($partner, $benefit);

        return Inertia::render('partner/content/form', ['partner' => $partner->only('name', 'slug'), 'kind' => 'benefit', 'item' => $benefit ? $benefit->load('locations')->toArray() + ['imageUrl' => app(\App\Services\MediaUrl::class)->url($benefit->image_path, 'thumbnail')] : null, 'locations' => $partner->locations()->orderBy('name')->get(['id', 'name'])]);
    }

    public function saveBenefit(Request $request, Partner $partner, ?Benefit $benefit = null)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $partner, $benefit) {
            $this->ownedBenefit($partner, $benefit);
            $this->editable($benefit);
            $data = $request->validate($this->benefitRules());
            $ids = $data['location_ids'] ?? [];
            abort_unless(Location::where('partner_id', $partner->id)->whereIn('id', $ids)->count() === count(array_unique($ids)), 422);
            $benefit ??= new Benefit(['partner_id' => $partner->id, 'status' => 'draft', 'review_status' => 'draft', 'created_by_user_id' => $request->user()->id]);
            $benefit->fill(collect($data)->except(['location_ids', 'location_scope'])->all());
            $benefit->applies_to_all_locations = $data['location_scope'] === 'all';
            $benefit->slug ??= $this->slug(Benefit::class, $data['title']);
            $benefit->save();
            $benefit->syncLocations($ids);
            $benefit->update(['image_path' => app(MediaUploadService::class)->fromRequest($request, 'image', 'benefits', $benefit->id, 'cover', $benefit->image_path)]);

            return to_route('partner.benefits.index', $partner);
        });
    }

    public function submitBenefit(Request $request, Partner $partner, Benefit $benefit)
    {
        $this->ownedBenefit($partner, $benefit);
        abort_unless(in_array($benefit->review_status, ['draft', 'changes_requested', 'rejected'], true), 403);
        $benefit->update(['review_status' => 'submitted', 'submitted_at' => now(), 'submitted_by_user_id' => $request->user()->id, 'review_notes' => null]);

        return back();
    }

    public function experiences(Partner $partner): Response
    {
        return Inertia::render('partner/content/index', ['partner' => $partner->only('name', 'slug'), 'kind' => 'experience', 'items' => \App\Support\ListingPage::make(Experience::whereHas('partners', fn ($q) => $q->whereKey($partner->id))->withCount('sessions')->latest())]);
    }

    public function experienceForm(Partner $partner, ?Experience $experience = null): Response
    {
        $this->ownedExperience($partner, $experience);

        return Inertia::render('partner/content/form', ['partner' => $partner->only('name', 'slug'), 'kind' => 'experience', 'item' => $experience ? $experience->load('sessions.location')->toArray() + ['imageUrl' => app(\App\Services\MediaUrl::class)->url($experience->image_path, 'thumbnail'), 'coverUrl' => app(\App\Services\MediaUrl::class)->url($experience->cover_path, 'thumbnail')] : null, 'locations' => $partner->locations()->orderBy('name')->get(['id', 'name'])]);
    }

    public function saveExperience(Request $request, Partner $partner, ?Experience $experience = null)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $partner, $experience) {
            $this->ownedExperience($partner, $experience);
            $this->editable($experience);
            $data = $request->validate($this->experienceRules());
            $experience ??= new Experience(['status' => 'draft', 'review_status' => 'draft', 'created_by_user_id' => $request->user()->id]);
            $experience->fill($data);
            $experience->slug ??= $this->slug(Experience::class, $data['title']);
            $experience->save();
            if (! $experience->partners()->whereKey($partner->id)->exists()) {
                $experience->partners()->attach($partner->id, ['role' => 'organizer', 'sort_order' => 0]);
            }
            $experience->update(['image_path' => app(MediaUploadService::class)->fromRequest($request, 'image', 'experiences', $experience->id, 'cover', $experience->image_path)]);
            $experience->update(['cover_path' => app(MediaUploadService::class)->fromRequest($request, 'cover', 'experiences', $experience->id, 'cover', $experience->cover_path)]);

            return to_route('partner.experiences.edit', [$partner, $experience]);
        });
    }

    public function saveSession(Request $request, Partner $partner, Experience $experience)
    {
        $this->ownedExperience($partner, $experience);
        $this->editable($experience);
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'capacity' => 'nullable|integer|min:1', 'venue_label' => 'nullable|string|max:255', 'location_id' => 'nullable|exists:locations,id']);
        abort_if(isset($data['location_id']) && ! Location::whereKey($data['location_id'])->where('partner_id', $partner->id)->exists(), 422);
        $experience->sessions()->create($data + ['status' => 'scheduled', 'reservation_partner_id' => $partner->id]);

        return back();
    }

    public function updateSession(Request $request, Partner $partner, Experience $experience, ExperienceSession $session)
    {
        $this->ownedExperience($partner, $experience);
        $this->editable($experience);
        abort_unless($session->experience_id === $experience->id, 404);
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'capacity' => 'nullable|integer|min:1', 'venue_label' => 'nullable|string|max:255', 'location_id' => 'nullable|exists:locations,id']);
        abort_if(isset($data['location_id']) && ! Location::whereKey($data['location_id'])->where('partner_id', $partner->id)->exists(), 422);
        $session->update($data + ['reservation_partner_id' => $partner->id]);

        return back();
    }

    public function submitExperience(Request $request, Partner $partner, Experience $experience)
    {
        $this->ownedExperience($partner, $experience);
        abort_unless(in_array($experience->review_status, ['draft', 'changes_requested', 'rejected'], true), 403);
        $experience->update(['review_status' => 'submitted', 'submitted_at' => now(), 'submitted_by_user_id' => $request->user()->id, 'review_notes' => null]);

        return back();
    }

    private function ownedBenefit(Partner $partner, ?Benefit $benefit): void
    {
        if ($benefit) {
            abort_unless($benefit->partner_id === $partner->id, 404);
        }
    }

    private function ownedExperience(Partner $partner, ?Experience $experience): void
    {
        if ($experience) {
            abort_unless($experience->partners()->whereKey($partner->id)->exists(), 404);
        }
    }

    private function editable(?object $content): void
    {
        if ($content) {
            abort_unless(in_array($content->review_status, ['draft', 'changes_requested', 'rejected'], true), 403);
        }
    }

    private function slug(string $class, string $title): string
    {
        $base = Str::slug($title) ?: 'contenido';
        $slug = $base;
        $n = 2;
        while ($class::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    private function benefitRules(): array
    {
        return ['title' => 'required|string|max:255', 'short_description' => 'nullable|string', 'description' => 'nullable|string', 'terms' => 'nullable|string', 'category' => 'nullable|string', 'benefit_type' => 'nullable|string', 'estimated_savings' => 'nullable|numeric|min:0', 'redemption_limit_per_member' => 'nullable|integer|min:1', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'location_scope' => 'required|in:all,selected', 'location_ids' => 'array', 'location_ids.*' => 'integer', 'image' => 'nullable|image|mimes:jpeg,png,webp|max:10240'];
    }

    private function experienceRules(): array
    {
        return ['title' => 'required|string|max:255', 'short_description' => 'nullable|string', 'description' => 'nullable|string', 'category' => 'nullable|string', 'experience_type' => 'nullable|string', 'duration_minutes' => 'nullable|integer|min:1', 'regular_price' => 'nullable|numeric|min:0', 'member_price' => 'nullable|numeric|min:0', 'reservation_method' => 'required|in:whatsapp,url,phone,external,jakawi,none', 'reservation_url' => 'nullable|url', 'reservation_whatsapp' => 'nullable|string', 'reservation_phone' => 'nullable|string', 'image' => 'nullable|image|mimes:jpeg,png,webp|max:10240', 'cover' => 'nullable|image|mimes:jpeg,png,webp|max:10240'];
    }
}
