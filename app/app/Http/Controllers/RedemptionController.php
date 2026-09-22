<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RedemptionController extends Controller
{
    public function store(Request $request, Benefit $benefit): RedirectResponse
    {
        $redemption = DB::transaction(function () use ($request, $benefit) {
            /** @var User $user */
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            $membership = $user->activeMembership()->lockForUpdate()->first();
            abort_unless($membership, 403);

            $benefit = Benefit::query()
                ->with('merchant')
                ->whereKey($benefit->id)
                ->available()
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($benefit->merchant->redemption_pin_hash, 422);
            abort_if($this->limitReached($user, $benefit), 403);

            $existing = Redemption::query()
                ->where('user_id', $user->id)
                ->where('benefit_id', $benefit->id)
                ->pendingValid()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return Redemption::query()->create([
                'code' => $this->uniqueCode(),
                'user_id' => $user->id,
                'membership_id' => $membership->id,
                'merchant_id' => $benefit->merchant_id,
                'benefit_id' => $benefit->id,
                'merchant_name' => $benefit->merchant->name,
                'benefit_title' => $benefit->title,
                'status' => Redemption::STATUS_PENDING,
                'savings_amount' => $benefit->estimated_savings,
                'expires_at' => now()->addMinutes((int) config('jakawi.redemption.code_ttl_minutes')),
            ]);
        });

        return to_route('redemptions.show', $redemption);
    }

    public function show(Request $request, Redemption $redemption): Response
    {
        abort_unless($redemption->user_id === $request->user()->id, 403);

        return Inertia::render('redemptions/show', [
            'redemption' => [
                'public_id' => $redemption->public_id,
                'code' => $redemption->code,
                'status' => $redemption->status,
                'merchant_name' => $redemption->merchant_name,
                'benefit_title' => $redemption->benefit_title,
                'benefit_slug' => $redemption->benefit?->slug,
                'savings_amount' => $redemption->savings_amount,
                'expires_at' => $redemption->expires_at?->toDateTimeString(),
                'confirmed_at' => $redemption->confirmed_at?->toDateTimeString(),
                'is_expired' => $redemption->isExpired(),
            ],
        ]);
    }

    private function limitReached(User $user, Benefit $benefit): bool
    {
        if ($benefit->redemption_limit_per_member === null) {
            return false;
        }

        return Redemption::query()
            ->where('user_id', $user->id)
            ->where('benefit_id', $benefit->id)
            ->confirmed()
            ->lockForUpdate()
            ->count() >= $benefit->redemption_limit_per_member;
    }

    private function uniqueCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            if (! Redemption::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate redemption code.');
    }
}
