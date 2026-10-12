<?php

namespace App\Services;

use App\Http\Controllers\PublicController;
use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Unlock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Admin projections only: no copied economics, eligibility or operational configuration. */
class LandingPresentationEditor
{
    public const SUBJECTS = ['BENEFIT' => Benefit::class, 'EXPERIENCE' => Experience::class, 'CHALLENGE' => Challenge::class];

    public const EDITORIAL = ['eyebrow', 'headline', 'headline_emphasis', 'subheadline', 'reward_display_override', 'final_cta_headline', 'final_cta_description'];

    public function type(Model $subject): string
    {
        return array_search($subject::class, self::SUBJECTS, true) ?: 'UNLOCK';
    }

    public function selected(string $type, int $id): Model
    {
        $class = self::SUBJECTS[$type] ?? null;
        if (! $class || ! ($subject = $class::find($id))) {
            throw ValidationException::withMessages(['subject_id' => 'Selecciona un producto existente del tipo indicado.']);
        }

        return $subject;
    }

    public function choices(): array
    {
        return collect(self::SUBJECTS)->map(fn ($class) => $class::query()->orderBy('title')->get(['id', 'title', 'slug']))->all();
    }

    public function context(Model $subject, Request $request): array
    {
        $type = $this->type($subject);
        $draft = new LandingPresentation;
        $draft->setRelation('subject', $subject);
        $copy = match ($type) {
            'BENEFIT' => app(BenefitMarketingLandingPresenter::class)->present($subject, $draft, $request, app(PublicController::class)->benefitPublicProps($subject, $request, true)),
            'EXPERIENCE' => app(ExperienceMarketingLandingPresenter::class)->present($subject, $draft, $request),
            'CHALLENGE' => app(ChallengeMarketingLandingPresenter::class)->present($subject, $draft),
            default => [],
        };
        $truth = match ($type) {
            'BENEFIT' => [['label' => 'Beneficio', 'value' => $copy['value']], ['label' => 'Partner', 'value' => $copy['partner']], ['label' => 'Membresía / acceso', 'value' => $copy['membership']], ['label' => 'Vigencia', 'value' => $copy['deadline'] ?? 'Consulta las condiciones'], ['label' => 'Requisitos', 'value' => implode(' · ', $copy['rules'])]],
            'EXPERIENCE' => [['label' => 'Fecha / hora', 'value' => $copy['next'] ? $copy['next']['date'].' · '.$copy['next']['time'].(! empty($copy['next']['endTime']) ? '–'.$copy['next']['endTime'] : '') : 'Sin próximas fechas'], ['label' => 'Lugar', 'value' => implode(' · ', array_filter([$copy['next']['place'] ?? null, $copy['next']['city'] ?? null])) ?: 'Consulta la ficha'], ...array_map(fn ($price) => ['label' => $price['label'], 'value' => $price['value']], $copy['prices']), ['label' => 'Reserva', 'value' => $copy['membership']]],
            'CHALLENGE' => [['label' => 'Premio', 'value' => $copy['reward']], ['label' => 'Cierre', 'value' => $copy['deadline'] ?? 'Consulta las reglas'], ['label' => 'Calificación', 'value' => $copy['qualification']], ['label' => 'Selección', 'value' => $copy['selection']], ['label' => 'Membresía', 'value' => $copy['membership']]],
            // Preserve Unlock disclosure: this editor never serializes its restricted operational data.
            default => [],
        };

        return [
            'id' => $subject->id, 'type' => $type, 'title' => $subject->title, 'slug' => $subject->slug,
            'defaults' => ['eyebrow' => $copy['eyebrow'] ?? '', 'headline' => $subject->title, 'subheadline' => $subject->short_description ?? '', 'hero_path' => $type === 'UNLOCK' ? '' : ($subject->hero_path ?? $subject->cover_path ?? $subject->image_path ?? ''), 'hero_alt' => $subject->title, 'marketing_theme' => $type === 'CHALLENGE' ? 'DARK_CONVERSION' : 'LIGHT_CONVERSION'],
            'truth' => $truth,
        ];
    }

    public function publicEditorial(LandingPresentation $presentation, array $copy): array
    {
        // Existing presenters retain their editorial/economics boundaries. The new closing copy
        // is validated on write; persisted economics and action semantics never enter this projection.
        return ['version' => $presentation->editorial_version, 'eyebrow' => $copy['eyebrow'], 'headline' => $copy['headline'], 'subheadline' => $copy['subheadline'],
            'emphasis' => $presentation->headline_emphasis, 'finalHeadline' => $copy['finalHeadline'], 'finalDescription' => $presentation->final_cta_description];
    }
}
