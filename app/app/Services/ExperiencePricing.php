<?php

namespace App\Services;

use App\Models\Experience;
use App\Models\User;

/** Display only. Experiences have no checkout or price enforcement in external handoff. */
class ExperiencePricing
{
    public function present(Experience $experience, ?User $viewer = null): array
    {
        $prices = [];
        $external = $experience->reservation_method !== 'jakawi';
        foreach (['regular_price', 'member_price'] as $field) {
            if ($experience->$field === null) {
                continue;
            }
            $memberPrice = $field === 'member_price';
            $label = $memberPrice ? 'Con membresía activa' : ($external ? 'Sin membresía' : 'Precio base · reserva solo con membresía');
            if ($external && $memberPrice) {
                $label = 'Precio preferencial previsto · activación y verificación pendientes';
            }
            $prices[] = [
                'label' => $label,
                'value' => (float) $experience->$field === 0.0 ? 'GRATIS' : ($experience->currency === 'BOB' ? 'Bs ' : $experience->currency.' ').$experience->$field,
                'memberAdvantage' => $memberPrice && ! $external && $viewer?->hasActiveMembership() && $experience->regular_price !== null && (float) $experience->member_price < (float) $experience->regular_price,
            ];
        }

        return $prices;
    }
}
