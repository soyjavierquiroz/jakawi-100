<?php

namespace App\Http\Controllers;

use App\Models\Benefit;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RedemptionValidationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('redemptions/validate');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'pin' => ['required', 'digits:6'],
        ]);

        $code = strtoupper($data['code']);
        $message = DB::transaction(function () use ($code, $data) {
            $candidate = Redemption::query()->where('code', $code)->first();

            if (! $candidate) {
                return ['error' => 'No pudimos validar ese código y PIN.'];
            }

            User::query()->whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();

            $redemption = Redemption::query()
                ->with(['merchant', 'benefit', 'membership'])
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if ($redemption->status === Redemption::STATUS_CONFIRMED) {
                return $this->validPin($redemption, $data['pin'])
                    ? ['success' => 'Este canje ya fue confirmado.']
                    : ['error' => 'No pudimos validar ese código y PIN.'];
            }

            if ($redemption->isExpired()) {
                $redemption->update(['status' => Redemption::STATUS_EXPIRED]);

                return ['error' => 'No pudimos validar ese código y PIN.'];
            }

            if (
                $redemption->status !== Redemption::STATUS_PENDING
                || ! $redemption->merchant
                || ! $redemption->benefit
                || ! $redemption->membership()->active()->exists()
                || ! Benefit::query()->whereKey($redemption->benefit_id)->available()->exists()
                || ! $this->validPin($redemption, $data['pin'])
                || $this->limitReached($redemption)
            ) {
                return ['error' => 'No pudimos validar ese código y PIN.'];
            }

            $redemption->update([
                'status' => Redemption::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            return ['success' => 'Canje confirmado.'];
        });

        return back()->with($message);
    }

    private function validPin(Redemption $redemption, string $pin): bool
    {
        return (bool) $redemption->merchant?->redemption_pin_hash
            && Hash::check($pin, $redemption->merchant->redemption_pin_hash);
    }

    private function limitReached(Redemption $redemption): bool
    {
        if ($redemption->benefit?->redemption_limit_per_member === null) {
            return false;
        }

        return Redemption::query()
            ->where('user_id', $redemption->user_id)
            ->where('benefit_id', $redemption->benefit_id)
            ->whereKeyNot($redemption->id)
            ->confirmed()
            ->lockForUpdate()
            ->count() >= $redemption->benefit->redemption_limit_per_member;
    }
}
