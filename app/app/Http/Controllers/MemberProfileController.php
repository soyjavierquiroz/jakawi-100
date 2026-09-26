<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use App\Services\MediaUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberProfileController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->isPartnerOnly()) {
            return to_route('partner.index');
        }

        $profile = $request->user()->profile;

        return Inertia::render('member-profile', [
            'user' => $request->user()->only('name', 'email'),
            'profile' => $this->serialize($profile),
            'options' => config('jakawi.member_profile'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_if($request->user()->isPartnerOnly(), 403);

        $options = config('jakawi.member_profile');
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
        $oldAvatarPath = $profile->avatar_path;
        $newAvatarPath = null;

        try {
            if ($request->hasFile('avatar')) {
                // Preserve the current avatar until the new key is committed.
                $newAvatarPath = $media->store($request->file('avatar'), 'avatars', $request->user()->id, 'avatar');
                $data['avatar_path'] = $newAvatarPath;
            }

            DB::transaction(function () use ($profile, $data): void {
                $profile->fill($data);
                if ($profile->completionPercentage() === 100 && $profile->profile_completed_at === null) {
                    $profile->profile_completed_at = now();
                }
                $profile->save();
            });
        } catch (\Throwable $exception) {
            $media->delete($newAvatarPath);

            throw $exception;
        }

        if ($newAvatarPath !== null) {
            $media->delete($oldAvatarPath);
        }

        return to_route('member.profile.show')->with('success', 'Tu perfil JAKAWI fue guardado.');
    }

    /** @return array<string, mixed> */
    public function serialize(?UserProfile $profile): array
    {
        return [
            'city' => $profile?->city,
            'avatar_url' => app(\App\Services\MediaUrl::class)->url($profile?->avatar_path, 'avatar'),
            'interests' => $profile?->interests ?? [],
            'social_contexts' => $profile?->social_contexts ?? [],
            'preferred_days' => $profile?->preferred_days ?? [],
            'preferred_times' => $profile?->preferred_times ?? [],
            'completion_percentage' => $profile?->completionPercentage() ?? 0,
            'profile_completed_at' => $profile?->profile_completed_at?->toDateTimeString(),
        ];
    }
}
