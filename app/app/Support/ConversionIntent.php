<?php

namespace App\Support;

use App\Models\Benefit;
use Illuminate\Http\Request;

class ConversionIntent
{
    public const SESSION_KEY = 'conversion.benefit_intent';

    public function store(Request $request, Benefit $benefit): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'benefit_id' => $benefit->id,
            'return_target' => 'benefit',
        ]);
    }

    public function benefitId(Request $request): ?int
    {
        $intent = $request->session()->get(self::SESSION_KEY);

        if (! is_array($intent) || ($intent['return_target'] ?? null) !== 'benefit' || ! is_int($intent['benefit_id'] ?? null)) {
            return null;
        }

        return $intent['benefit_id'];
    }

    public function paywallPath(Request $request): ?string
    {
        $benefitId = $this->benefitId($request);
        if ($benefitId === null) {
            return null;
        }

        $benefit = Benefit::query()->available()->find($benefitId);

        return $benefit ? route('membership.paywall', $benefit, false) : null;
    }
}
