<?php

namespace App\Http\Controllers;

use App\Enums\LandingMediaMode;
use App\Enums\LandingTemplate;
use App\Enums\MarketingTheme;
use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Unlock;
use App\Services\LandingPresentationDefaults;
use App\Services\LandingPresentationEditor;
use App\Services\LandingVideoConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminLandingPresentationController extends Controller
{
    public function index(Request $request, string $subjectSlug = '')
    {
        $subject = $request->route()->hasParameter('unlock') || $request->route()->hasParameter('experience') || $request->route()->hasParameter('benefit') || $request->route()->hasParameter('challenge') ? $this->subject($request) : ($request->filled('subject_type') && $request->filled('subject_id') ? app(LandingPresentationEditor::class)->selected($request->string('subject_type')->toString(), $request->integer('subject_id')) : null);
        $editor = app(LandingPresentationEditor::class);

        return Inertia::render('admin/landing-presentations/index', [
            'subject' => $subject ? $editor->context($subject, $request) : null,
            'subjectChoices' => $editor->choices(),
            'autoCreate' => $request->boolean('create'),
            'baseUrl' => $subject ? $this->baseUrl($subject) : '/admin/landings',
            'productUrl' => ! $subject ? '/admin' : ($subject instanceof Unlock ? route('admin.unlocks.edit', $subject) : ($subject instanceof Experience ? route('admin.experiences.edit', $subject) : ($subject instanceof Benefit ? route('admin.benefits.edit', $subject) : '/admin/retos/'.$subject->slug))),
            'presentations' => $subject ? $subject->landingPresentations()->orderBy('id')->get() : [],
        ]);
    }

    public function subjectContext(Request $request)
    {
        $editor = app(LandingPresentationEditor::class);
        $data = $request->validate(['subject_type' => ['required', Rule::in(array_keys($editor::SUBJECTS))], 'subject_id' => ['required', 'integer']]);

        return response()->json($editor->context($editor->selected($data['subject_type'], (int) $data['subject_id']), $request));
    }

    public function save(Request $request, string $subjectSlug, ?LandingPresentation $presentation = null)
    {
        $subject = $this->subject($request);
        if ($presentation) {
            $this->belongsTo($subject, $presentation);
        }
        $editor = app(LandingPresentationEditor::class);
        $target = $subject;
        if ($request->hasAny(['subject_type', 'subject_id'])) {
            abort_if($subject instanceof Unlock, 422, 'Unlock conserva su administración de subject actual.');
            $selection = $request->validate(['subject_type' => ['required', Rule::in(array_keys($editor::SUBJECTS))], 'subject_id' => ['required', 'integer']]);
            $target = $editor->selected($selection['subject_type'], (int) $selection['subject_id']);
        }
        $offer = ! ($target instanceof Unlock);
        $plain = function ($attribute, $value, $fail) {
            if (is_string($value) && preg_match('/[<>`]|\*\*|__|!?\[[^\]]*\]\(|^\s*#+\s/mu', $value)) {
                $fail('Usa texto plano, sin HTML ni Markdown.');
            }
        };
        $editorial = function ($attribute, $value, $fail) use ($target, $presentation) {
            if ($value === $presentation?->getAttribute($attribute) || ($attribute === 'headline' && $value === $target->title) || ($attribute === 'subheadline' && $value === $target->short_description)) {
                return;
            }
            if (is_string($value) && preg_match('/\d|%|\b(?:gratis|gratuito|sin membresía|sin límites|precio|cupos|garantizado|cero|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|cien|mil|doble)\b/iu', $value)) {
                $fail('Los importes, cantidades y condiciones de acceso pertenecen al producto.');
            }
        };
        $rules = [
            ...LandingVideoConfiguration::rules(),
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('landing_presentations', 'slug')->ignore($presentation?->id)],
            'campaign_key' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'eyebrow' => ['nullable', 'string', 'max:100', $plain],
            'headline' => ['nullable', 'string', 'max:180', $plain, $editorial],
            'headline_emphasis' => ['nullable', 'string', 'max:180', $plain],
            'subheadline' => ['nullable', 'string', 'max:1000', $plain, $editorial],
            'hero_path' => ['nullable', 'string', 'max:2048'], 'hero_alt' => ['nullable', 'string', 'max:255', $plain],
            'reward_display_override' => ['nullable', 'string', 'max:180', $plain, $editorial],
            'final_cta_headline' => ['nullable', 'string', 'max:180', $plain, $editorial],
            'final_cta_description' => ['nullable', 'string', 'max:1000', $plain, $editorial],
            'default_scope' => ['sometimes', Rule::in(LandingPresentation::SCOPES)],
            'template_key' => $offer ? ['sometimes', 'required', Rule::enum(LandingTemplate::class)] : ['prohibited'],
            'marketing_theme' => $offer ? ['sometimes', 'required', Rule::enum(MarketingTheme::class)] : ['prohibited'],
            'media_mode' => $offer ? ['sometimes', 'required', Rule::enum(LandingMediaMode::class)] : ['prohibited'],
        ];
        if (! $offer) {
            // Existing Unlock editorial management is outside Offer Conversion V1.
            foreach (['eyebrow', 'headline', 'subheadline', 'hero_alt', 'reward_display_override', 'final_cta_headline'] as $field) {
                $rules[$field] = array_values(array_filter($rules[$field], 'is_string'));
            }
            $rules['headline_emphasis'] = ['prohibited'];
            $rules['final_cta_description'] = ['prohibited'];
        }
        foreach (['action', 'cta_url', 'cta_kind', 'cta_payload', 'cta_endpoint', 'custom_css', 'sections', 'prices', 'eligibility', 'rules', 'status', 'editorial_version', 'publish_scope'] as $forbidden) {
            $rules[$forbidden] = ['prohibited'];
        }
        if ($offer) {
            // Preserve an existing custom player configuration unchanged, without adding a color editor.
            $rules['vsl.colors'][] = function ($attribute, $value, $fail) use ($presentation) {
                if ($value !== ($presentation?->vsl['colors'] ?? null)) {
                    $fail('No se permite personalizar colores.');
                }
            };
            $rules['vsl.theme'][] = Rule::in(['inherit', $presentation?->vsl['theme'] ?? 'inherit']);
        }
        $data = $request->validate($rules);
        $headline = $data['headline'] ?? null;
        if (! empty($data['headline_emphasis']) && (! $headline || ! str_contains($headline, $data['headline_emphasis']))) {
            throw ValidationException::withMessages(['headline_emphasis' => 'El texto destacado debe estar contenido en el headline.']);
        }
        $scope = $data['default_scope'] ?? ($presentation?->status === 'PUBLISHED' ? $presentation->default_scope : ($presentation?->publish_scope ?? 'NONE'));
        unset($data['default_scope']);
        if ($offer) {
            $data['template_key'] ??= 'OFFER_CONVERSION';
            $data['marketing_theme'] ??= $presentation?->marketing_theme?->value ?? ($target instanceof Challenge ? 'DARK_CONVERSION' : 'LIGHT_CONVERSION');
            $data['media_mode'] ??= $request->has('vsl') ? ($data['vsl'] ? 'VSL' : 'IMAGE') : ($presentation?->media_mode?->value ?? 'IMAGE');
            if ($data['media_mode'] === 'VSL' && ! (array_key_exists('vsl', $data) ? $data['vsl'] : $presentation?->vsl)) {
                throw ValidationException::withMessages(['vsl' => 'Configura el video antes de seleccionarlo.']);
            }
            $changed = ! $presentation || $target->getMorphClass() !== $presentation->subject_type || $target->id !== $presentation->subject_id;
            foreach ($editor::EDITORIAL as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== $presentation?->$field) {
                    $changed = true;
                }
            }
            $data['editorial_version'] = $changed ? 1 : ($presentation?->editorial_version ?? 1);
        }
        $data['publish_scope'] = $scope;
        DB::transaction(function () use ($subject, $target, $presentation, $data, $scope) {
            foreach (collect([$subject, $target])->unique(fn ($s) => $s->getMorphClass().':'.$s->id)->sortBy(fn ($s) => $s->getMorphClass().':'.$s->id) as $locked) {
                $locked->newQuery()->whereKey($locked->id)->lockForUpdate()->firstOrFail();
            }
            if ($presentation) {
                $presentation = LandingPresentation::whereKey($presentation->id)->lockForUpdate()->firstOrFail();
                $this->belongsTo($subject, $presentation);
                if ($presentation->status === 'PUBLISHED') {
                    $this->assertPublishable($target);
                }
                $presentation->update(['default_scope' => 'NONE', 'subject_type' => $target->getMorphClass(), 'subject_id' => $target->id] + $data);
            } else {
                $presentation = $target->landingPresentations()->create($data + ['status' => 'DRAFT', 'default_scope' => 'NONE']);
            }
            if ($presentation->status === 'PUBLISHED' && $scope !== 'NONE') {
                app(LandingPresentationDefaults::class)->choose($target, $presentation, $scope);
            }
        });

        return redirect($this->baseUrl($target));
    }

    public function publish(Request $request, string $subjectSlug, LandingPresentation $presentation)
    {
        $subject = $this->subject($request);
        $this->belongsTo($subject, $presentation);
        DB::transaction(function () use ($subject, $presentation) {
            $subject->newQuery()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            $presentation = LandingPresentation::whereKey($presentation->id)->lockForUpdate()->firstOrFail();
            $this->belongsTo($subject, $presentation);
            $this->assertPublishable($subject);
            $presentation->update(['status' => 'PUBLISHED']);
            if ($presentation->publish_scope !== 'NONE') {
                app(LandingPresentationDefaults::class)->choose($subject, $presentation, $presentation->publish_scope);
            }
        });

        return back();
    }

    public function archive(Request $request, string $subjectSlug, LandingPresentation $presentation)
    {
        $subject = $this->subject($request);
        $this->belongsTo($subject, $presentation);
        DB::transaction(function () use ($subject, $presentation) {
            $subject->newQuery()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            $presentation->update(['status' => 'ARCHIVED', 'default_scope' => 'NONE']);
        });

        return back();
    }

    public function useDefault(Request $request, string $subjectSlug, LandingPresentation $presentation, LandingPresentationDefaults $defaults)
    {
        $subject = $this->subject($request);
        $this->belongsTo($subject, $presentation);
        $data = $request->validate(['default_scope' => ['required', Rule::in(['GUESTS', 'ALL'])]]);
        $defaults->choose($subject, $presentation, $data['default_scope']);
        $presentation->update(['publish_scope' => $data['default_scope']]);

        return back();
    }

    public function productDefault(Request $request, string $subjectSlug, LandingPresentationDefaults $defaults)
    {
        $subject = $this->subject($request);
        $defaults->choose($subject, null, 'NONE');
        $subject->landingPresentations()->where('status', 'PUBLISHED')->update(['publish_scope' => 'NONE']);

        return back();
    }

    public function duplicate(Request $request, string $subjectSlug, LandingPresentation $presentation)
    {
        $subject = $this->subject($request);
        $this->belongsTo($subject, $presentation);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('landing_presentations', 'slug')], 'campaign_key' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']]);
        $clone = $presentation->replicate();
        $clone->fill($data + ['campaign_key' => $presentation->campaign_key]);
        $clone->status = 'DRAFT';
        $clone->default_scope = 'NONE';
        $clone->publish_scope = $presentation->status === 'PUBLISHED' ? $presentation->default_scope : $presentation->publish_scope;
        $clone->save();

        return redirect($this->baseUrl($subject));
    }

    public function unpublish(Request $request, string $subjectSlug, LandingPresentation $presentation)
    {
        $subject = $this->subject($request);
        $this->belongsTo($subject, $presentation);
        DB::transaction(function () use ($subject, $presentation) {
            $subject->newQuery()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            $presentation = LandingPresentation::whereKey($presentation->id)->lockForUpdate()->firstOrFail();
            $this->belongsTo($subject, $presentation);
            $presentation->update(['status' => 'DRAFT', 'default_scope' => 'NONE']);
        });

        return back();
    }

    private function assertPublishable(Model $subject): void
    {
        if (! ($subject instanceof Unlock ? in_array($subject->status, [Unlock::ACTIVE, Unlock::GOAL_REACHED, Unlock::UNLOCKED], true) : ($subject instanceof Experience ? $subject->isPublished() : ($subject instanceof Benefit ? $subject->isPublished() && $subject->partner?->isPublished() : $subject->isPublic())))) {
            throw ValidationException::withMessages(['presentation' => 'Publica el producto y su partner antes de publicar su landing.']);
        }
    }

    private function subject(Request $request): Model
    {
        return $request->route()->hasParameter('unlock') ? Unlock::where('slug', $request->route('unlock'))->firstOrFail() : ($request->route()->hasParameter('experience') ? Experience::where('slug', $request->route('experience'))->firstOrFail() : ($request->route()->hasParameter('benefit') ? Benefit::where('slug', $request->route('benefit'))->firstOrFail() : Challenge::where('slug', $request->route('challenge'))->firstOrFail()));
    }

    private function baseUrl(Model $subject): string
    {
        return '/admin/'.($subject instanceof Unlock ? 'desbloqueos' : ($subject instanceof Experience ? 'experiencias' : ($subject instanceof Benefit ? 'beneficios' : 'retos'))).'/'.$subject->slug.'/landings';
    }

    private function belongsTo(Model $subject, LandingPresentation $presentation): void
    {
        abort_unless($presentation->subject_type === $subject->getMorphClass() && $presentation->subject_id === $subject->id, 404);
    }
}
