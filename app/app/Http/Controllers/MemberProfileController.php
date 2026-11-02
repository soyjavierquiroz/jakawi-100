<?php

namespace App\Http\Controllers;

use App\Concerns\ProfileValidationRules;
use App\Models\Partner;
use App\Models\UserProfile;
use App\Services\Commercial\MembershipAcquisition;
use App\Services\MediaUploadService;
use App\Services\MediaUrl;
use App\Services\MembershipStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberProfileController extends Controller
{
    use ProfileValidationRules;

    public function show(Request $request): Response|RedirectResponse
    {
        $profile = $request->user()->profile;

        return Inertia::render('member-profile', [
            'user' => $request->user()->only('name', 'email', 'email_verified_at', 'marketing_opt_in'),
            'membershipStatus' => app(MembershipStatus::class)->for($request->user()),
            'acquisitionOrigin' => $this->origin($request),
            'profile' => $this->serialize($profile),
            'options' => config('jakawi.member_profile'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $options = config('jakawi.member_profile');
        $identity = $request->validate([
            ...array_intersect_key($this->profileRules($request->user()->id), $request->only('name', 'email')),
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ]);
        $data = $request->validate([
            'city' => ['nullable', 'string', Rule::in($options['cities'])],
            'interests' => ['nullable', 'array', 'max:10'],
            'interests.*' => ['string', Rule::in($options['interests'])],
            'social_contexts' => ['nullable', 'array', 'max:4'],
            'social_contexts.*' => ['string', Rule::in($options['social_contexts'])],
            'preferred_days' => ['nullable', 'array', 'max:2'],
            'preferred_days.*' => ['string', Rule::in($options['preferred_days'])],
            'preferred_times' => ['nullable', 'array', 'max:3'],
            'preferred_times.*' => ['string', Rule::in($options['preferred_times'])],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $profile = $request->user()->profile()->firstOrCreate([]);
        foreach (['interests', 'social_contexts', 'preferred_days', 'preferred_times'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = array_values(array_unique($data[$field] ?? []));
            }
        }

        unset($data['avatar']);
        $media = app(MediaUploadService::class);
        $data['avatar_path'] = $media->fromRequest($request, 'avatar', 'avatars', $request->user()->id, 'avatar', $profile->avatar_path);
        DB::transaction(function () use ($profile, $data, $identity, $request): void {
            $user = $request->user();
            $user->fill($identity);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            if ($user->isDirty('marketing_opt_in')) {
                $user->marketing_opt_in_at = now();
                $user->marketing_opt_in_source = 'profile';
            }
            if ($user->isDirty()) {
                $user->save();
            }
            $profile->fill($data);
            if ($profile->completionPercentage() === 100 && $profile->profile_completed_at === null) {
                $profile->profile_completed_at = now();
            }
            $profile->save();
        });

        return to_route('consumer.profile')->with('success', 'Tu perfil JAKAWI fue guardado.');
    }

    private function origin(Request $request): ?array
    {
        $origin = app(MembershipAcquisition::class)->original($request->user());
        if (($origin['kind'] ?? null) !== 'partner') {
            return null;
        }
        $partner = Partner::find($origin['partner_id']);

        return $partner ? ['kind' => 'partner', 'name' => $partner->name] : null;
    }

    /** @return array<string, mixed> */
    public function serialize(?UserProfile $profile): array
    {
        return [
            'city' => $profile?->city,
            'avatar_url' => app(MediaUrl::class)->url($profile?->avatar_path, 'avatar'),
            'interests' => $profile?->interests ?? [],
            'social_contexts' => $profile?->social_contexts ?? [],
            'preferred_days' => $profile?->preferred_days ?? [],
            'preferred_times' => $profile?->preferred_times ?? [],
            'completion_percentage' => $profile?->completionPercentage() ?? 0,
            'profile_completed_at' => $profile?->profile_completed_at?->toDateTimeString(),
        ];
    }
}
