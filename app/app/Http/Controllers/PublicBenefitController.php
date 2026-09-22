<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Redemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicBenefitController extends Controller
{
    public function index(): Response
    {
        $benefits = Benefit::query()
            ->with('merchant')
            ->available()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest()
            ->get()
            ->map(fn (Benefit $benefit) => $this->serializeBenefit($benefit));

        return Inertia::render('benefits/index', [
            'benefits' => $benefits,
        ]);
    }

    public function show(Request $request, Benefit $benefit): Response
    {
        abort_unless(
            Benefit::query()->whereKey($benefit->id)->available()->exists(),
            404,
        );

        $benefit->load('merchant');

        $user = $request->user();
        $hasActiveMembership = (bool) $user?->hasActiveMembership();
        $limitReached = $user ? $this->limitReached($user->id, $benefit) : false;

        return Inertia::render('benefits/show', [
            'benefit' => $this->serializeBenefit($benefit, detailed: true),
            'canRegister' => ! $request->user(),
            'hasActiveMembership' => $hasActiveMembership,
            'redemptionAvailability' => [
                'can_redeem' => $hasActiveMembership && (bool) $benefit->merchant->redemption_pin_hash && ! $limitReached,
                'limit_reached' => $limitReached,
                'temporarily_unavailable' => $hasActiveMembership && ! $benefit->merchant->redemption_pin_hash,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public static function serializeBenefit(Benefit $benefit, bool $detailed = false): array
    {
        $data = [
            'id' => $benefit->id,
            'title' => $benefit->title,
            'slug' => $benefit->slug,
            'short_description' => $benefit->short_description,
            'estimated_savings' => $benefit->estimated_savings,
            'image_url' => $benefit->image_path ? Storage::url($benefit->image_path) : null,
            'is_featured' => $benefit->is_featured,
            'merchant' => [
                'name' => $benefit->merchant->name,
                'category' => $benefit->merchant->category,
                'address' => $benefit->merchant->address,
                'city' => $benefit->merchant->city,
            ],
        ];

        if ($detailed) {
            $data += [
                'description' => $benefit->description,
                'terms' => $benefit->terms,
                'benefit_type' => $benefit->benefit_type,
                'starts_at' => $benefit->starts_at?->toDateTimeString(),
                'ends_at' => $benefit->ends_at?->toDateTimeString(),
            ];
        }

        return $data;
    }

    private function limitReached(int $userId, Benefit $benefit): bool
    {
        if ($benefit->redemption_limit_per_member === null) {
            return false;
        }

        return Redemption::query()
            ->where('user_id', $userId)
            ->where('benefit_id', $benefit->id)
            ->confirmed()
            ->count() >= $benefit->redemption_limit_per_member;
    }
}
