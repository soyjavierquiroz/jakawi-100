<?php

namespace App\Services;

use App\Models\LandingPresentation;

class LandingAcquisition
{
    public function defaults(string $type, string $title): array
    {
        return [
            'acquisition_headline' => match ($type) {
                'BENEFIT' => "Crea tu cuenta y continúa con tu beneficio: {$title}",
                'EXPERIENCE' => "Crea tu cuenta y vive esta experiencia: {$title}",
                'CHALLENGE' => "Crea tu cuenta y continúa con el reto: {$title}",
                default => "Crea tu cuenta y continúa con este desbloqueo: {$title}",
            },
            'acquisition_supporting_copy' => 'Dinos quién eres. Te toma menos de un minuto y seguimos con este mismo '.match ($type) {
                'BENEFIT' => 'beneficio.', 'EXPERIENCE' => 'plan.', 'CHALLENGE' => 'reto.', default => 'desbloqueo.',
            },
        ];
    }

    public function present(LandingPresentation $presentation, array $copy): array
    {
        $subject = $presentation->subject;
        $type = app(LandingPresentationEditor::class)->type($subject);
        $defaults = $this->defaults($type, $subject->title);
        // Provider comes from domain truth; Unlock uses only its disclosed presenter data.
        $partner = $copy['partner'] ?? null;
        $provider = is_string($partner) ? $partner : ($partner['name'] ?? null);
        if ($type === 'EXPERIENCE') {
            $provider = implode(' · ', array_unique(array_column(($copy['organizers'] ?? []) ?: ($copy['partners'] ?? []), 'name'))) ?: null;
        }

        if ($type === 'CHALLENGE') $provider = $subject->partner?->name;

        return [
            'passwordRules' => \Illuminate\Validation\Rules\Password::default()->toPasswordRulesString(),
            'type' => $type, 'subjectId' => $subject->id, 'title' => $subject->title, 'provider' => $provider,
            'headline' => $presentation->acquisition_headline ?: $defaults['acquisition_headline'],
            'supportingCopy' => $presentation->acquisition_supporting_copy ?: $defaults['acquisition_supporting_copy'],
            'endpoint' => route('landing-presentations.acquisition', $presentation, false),
        ];
    }
}
