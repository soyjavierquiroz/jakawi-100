<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Model;

/** Output-only public allowlist. Never serializes models, viewer state or Landing overrides. */
final class LandingAiBriefBuilder
{
    public const VERSION = 'jakawi-product-brief-v1';
    private const TIMEZONE = 'America/La_Paz';

    public function build(Model $subject, array $copy): ?array
    {
        $domain = match (true) {
            $subject instanceof Benefit => 'Benefit',
            $subject instanceof Experience => 'Experience',
            $subject instanceof Challenge => 'Challenge',
            default => null,
        };
        if ($domain === null) return null;

        $truth = match ($domain) {
            'Benefit' => $this->benefit($subject, $copy),
            'Experience' => $this->experience($subject, $copy),
            'Challenge' => $this->challenge($subject, $copy),
        };
        $cities = array_values(array_unique(array_filter(array_column($truth['locations'] ?? $truth['sessions'] ?? [], 'city'))));
        $demo = preg_match('/\s[—–-]\s*DEMO$/iu', $subject->title) === 1;

        return $this->clean([
            'brief_version' => self::VERSION,
            'domain' => $domain,
            'title' => $subject->title,
            'partner' => $domain === 'Experience' ? implode(' · ', array_unique(array_column($copy['partners'], 'name'))) : $subject->partner?->name,
            'city' => $domain === 'Challenge' ? $subject->city?->name : (count($cities) === 1 ? $cities[0] : null),
            'summary' => $subject->short_description,
            'description' => $subject->description,
            // Benefit Product Detail defines the DEMO suffix and this exact disclosure.
            // Other domains have no canonical false/demo-disclosure policy yet.
            'demo' => $domain === 'Benefit' ? $demo : ($demo ? true : null),
            'demo_disclosure' => $domain === 'Benefit' && $demo ? 'DEMO DE PRODUCTO · oferta simulada, no canjeable' : null,
            'commercial_truth' => $truth,
            'marketing_context' => [
                'primary_goal' => 'Conseguir que la persona reclame o active esta oferta específica',
                'landing_language' => 'es',
                'city_first' => true,
            ],
        ]);
    }

    private function benefit(Benefit $subject, array $copy): array
    {
        return [
            'benefit_type' => $subject->benefit_type,
            // No contractual numeric value exists. estimated_savings is never a discount.
            'value' => ['description' => $subject->title],
            'membership_required' => $subject->access_mode === Benefit::ACCESS_CHALLENGE_GRANT ? null : $subject->requiresMembership(),
            'access_requirement' => $copy['membership'],
            'available' => $subject->isAvailable(),
            'validity' => $this->validity($subject),
            'locations' => array_map(fn ($place) => [
                'name' => $place['name'], 'city' => $place['city'], 'area' => $place['zone'], 'address' => $place['address'],
            ], collect($copy['places'])->sortBy(fn ($place) => $place['name'].'|'.$place['address'])->values()->all()),
            'usage_limit' => $subject->redemption_limit_per_member !== null ? ['redemptions_per_member' => (int) $subject->redemption_limit_per_member] : null,
            'requirements' => [$copy['membership']],
            'conditions' => $subject->terms ? [$subject->terms] : [],
        ];
    }

    private function experience(Experience $subject, array $copy): array
    {
        $mode = match ($subject->reservation_method) {
            'jakawi' => 'internal',
            'whatsapp', 'url', 'phone', 'external' => 'external',
            'none' => 'informational',
            default => null,
        };
        $sessions = $subject->upcomingSessions()->with('location.cityEntity')->orderBy('id')->get()->map(fn ($session) => [
            'starts_at' => $this->date($session->starts_at),
            'ends_at' => $this->date($session->ends_at),
            'timezone' => self::TIMEZONE,
            'venue' => $session->location?->name ?: $session->venue_label,
            'address' => $session->location?->address,
            'city' => $session->location?->cityEntity?->name ?: $session->location?->city,
            // Total configured capacity, never remaining availability.
            'capacity' => $session->capacity,
        ])->all();

        return [
            'experience_type' => $subject->experience_type,
            'duration_minutes' => $subject->duration_minutes,
            'prices' => [
                'currency' => $subject->regular_price !== null || $subject->member_price !== null ? $subject->currency : null,
                'regular_price' => $subject->regular_price,
                'member_price' => $subject->member_price,
            ],
            'membership_required' => $mode === 'internal' ? true : null,
            'access_requirement' => $mode !== 'informational' ? $copy['membership'] : null,
            'reservation_mode' => $mode,
            'available_sessions' => count($sessions),
            'sessions' => $sessions,
            'conditions' => $subject->terms ? [$subject->terms] : [],
        ];
    }

    private function challenge(Challenge $subject, array $copy): array
    {
        $delivery = app(ChallengeDeliveryPresenter::class)->present($subject);
        $social = $subject->evidence_type === 'SOCIAL_POST';

        return [
            'objective' => $subject->instructions,
            'participation_eligibility' => match ($subject->participation_eligibility) {
                'ACTIVE_MEMBERS' => 'Personas con cuenta y membresía JAKAWI activa.',
                'ALL_USERS' => 'Personas con una cuenta JAKAWI.',
                default => null,
            },
            'membership_required' => match ($subject->participation_eligibility) {
                'ACTIVE_MEMBERS' => true, 'ALL_USERS' => false, default => null,
            },
            'access_requirement' => $copy['membership'],
            'platforms' => $social ? $subject->allowed_platforms : null,
            'evidence_type' => match ($subject->evidence_type) {
                'SOCIAL_POST' => 'Enlace de una publicación social.',
                'MANUAL' => 'Evidencia revisada manualmente por JAKAWI.',
                default => null,
            },
            'evidence_requirement' => $subject->instructions,
            'required_hashtags' => $social ? $subject->required_hashtags : null,
            'required_mentions' => $social ? $subject->required_mentions : null,
            'qualification' => $copy['qualification'],
            'validity' => $this->validity($subject),
            'entry_limit_per_person' => $subject->max_entries_per_user,
            'selection' => $copy['selection'],
            'winner_limit' => in_array($subject->selection_type, ['FIRST_N', 'TOP_N', 'MANUAL'], true) ? $subject->winner_limit : null,
            'reward' => match ($subject->reward_type) {
                'JP' => $subject->reward_jp_amount !== null ? $copy['reward'] : null,
                'MANUAL_PRIZE' => $subject->manual_prize_description,
                'BENEFIT' => $subject->benefit?->title,
                default => null,
            },
            'reward_access_requirement' => match ($subject->reward_eligibility) {
                'ACTIVE_MEMBERS' => 'Membresía JAKAWI activa para recibir el premio.',
                'ALL_USERS' => 'No se requiere membresía activa para recibir el premio.',
                default => null,
            },
            'review_required' => $subject->review_mode === 'REQUIRED' || $subject->evidence_type === 'MANUAL' || $subject->qualification_type === 'MANUAL',
            'verification_boundary' => $delivery['verificationBoundary'],
        ];
    }

    private function validity(Model $subject): ?array
    {
        if (! $subject->starts_at && ! $subject->ends_at) return null;
        return ['starts_at' => $this->date($subject->starts_at), 'ends_at' => $this->date($subject->ends_at), 'timezone' => self::TIMEZONE];
    }

    private function date($date): ?string
    {
        return $date?->copy()->timezone(self::TIMEZONE)->toIso8601String();
    }

    /** Defense in depth for contact data embedded in otherwise public text. Omit, don't rewrite. */
    private function clean(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) $value = $this->clean($value);
            if ($value === null || $value === '' || $value === []) continue;
            if (is_string($value) && preg_match('~https?://|www\.|[\w.+-]+@[\w.-]+\.[a-z]{2,}|\+?\d[\d ()-]{6,}\d|\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b~iu', $value) && ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value)) continue;
            $result[$key] = $value;
        }
        return array_is_list($data) ? array_values($result) : $result;
    }
}
