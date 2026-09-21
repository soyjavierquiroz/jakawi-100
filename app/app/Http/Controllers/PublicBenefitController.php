<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
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

        return Inertia::render('benefits/show', [
            'benefit' => $this->serializeBenefit($benefit, detailed: true),
            'canRegister' => ! $request->user(),
            'hasActiveMembership' => (bool) $request->user()?->hasActiveMembership(),
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
}
