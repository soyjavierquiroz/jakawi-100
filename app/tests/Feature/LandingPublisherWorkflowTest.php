<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingPublisherWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapped_import_fields_persist_reload_and_project_into_saved_preview(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $subject = Experience::factory()->published()->create(['title' => 'Arcilla y manos — DEMO', 'cover_path' => 'qa-clay.svg']);
        $base = '/admin/experiencias/'.$subject->slug.'/landings';
        $fields = [
            'name' => 'Arcilla y manos — adquisición demo', 'slug' => 'arcilla-y-manos-demo',
            'template_key' => 'OFFER_CONVERSION', 'marketing_theme' => 'DARK_CONVERSION',
            'hero_layout' => 'MEDIA_LED', 'density' => 'COMPACT', 'eyebrow' => 'CREA CON TUS MANOS',
            'headline' => 'DEJA EL SCROLL. ENSÚCIATE LAS MANOS.', 'headline_emphasis' => 'ENSÚCIATE LAS MANOS.',
            'subheadline' => 'Descubre tu forma de crear con arcilla.', 'reward_display_override' => 'Un plan para crear.',
            'acquisition_headline' => 'VAMOS A METER LAS MANOS EN LA ARCILLA.', 'acquisition_supporting_copy' => 'Crea tu cuenta y sigue aquí.',
            'final_cta_headline' => 'MENOS PANTALLA. MÁS ARCILLA.', 'final_cta_description' => 'Dale espacio a tu creatividad.',
            'media_mode' => 'IMAGE', 'hero_alt' => 'Manos trabajando arcilla',
        ];
        $this->post($base, $fields)->assertRedirect($base)->assertSessionHasNoErrors();
        $landing = LandingPresentation::where('slug', $fields['slug'])->sole();
        foreach ($fields as $key => $value) {
            $this->assertEquals($value, $landing->getRawOriginal($key), $key);
        }
        $this->get($base)->assertInertia(function (Assert $page) use ($fields) {
            foreach ($fields as $key => $value) $page->where('presentations.0.'.$key, $value);
            $page->where('presentations.0.editorHeroUrl', fn ($url) => str_ends_with($url, '/storage/qa-clay.svg'));
        });
        $this->get($base.'/'.$landing->slug.'/preview')->assertInertia(fn (Assert $page) => $page
            ->where('offerEditorial.headline', $fields['headline'])->where('offerEditorial.emphasis', $fields['headline_emphasis'])
            ->where('offerEditorial.subheadline', $fields['subheadline'])->where('offerEditorial.eyebrow', $fields['eyebrow'])
            ->where('offerEditorial.finalHeadline', $fields['final_cta_headline'])->where('offerEditorial.finalDescription', $fields['final_cta_description'])
            ->where('acquisition.headline', $fields['acquisition_headline'])->where('acquisition.supportingCopy', $fields['acquisition_supporting_copy'])
            ->where('copy.valueNote', $fields['reward_display_override'])->where('presentation.hero_layout', 'MEDIA_LED')->where('presentation.density', 'COMPACT')
            ->where('presentation.marketing_theme', 'DARK_CONVERSION')->where('presentation.template_key', 'OFFER_CONVERSION')
            ->where('presentation.hero_alt', $fields['hero_alt']));
        // Unchanged own slug remains valid; changing to another record's slug rejects without writes.
        $this->put($base.'/'.$landing->slug, $fields)->assertSessionHasNoErrors();
        $subject->landingPresentations()->create(['name' => 'Other draft', 'slug' => 'arcilla-y-manos-demo-collision', 'status' => 'DRAFT']);
        $this->put($base.'/'.$landing->slug, [...$fields, 'slug' => 'arcilla-y-manos-demo-collision', 'headline' => 'COPY MUST NOT PERSIST'])
            ->assertSessionHasErrors(['slug' => 'El slug "arcilla-y-manos-demo-collision" ya está en uso.']);
        foreach ($fields as $key => $value) $this->assertEquals($value, $landing->fresh()->getRawOriginal($key), $key);
    }

    public function test_arcilla_editorial_prose_saves_without_changing_domain_truth(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $subject = Experience::factory()->published()->create();
        $domain = $subject->fresh()->getAttributes();
        $base = '/admin/experiencias/'.$subject->slug.'/landings';
        $landing = $subject->landingPresentations()->create(['name' => 'Old', 'slug' => 'old-draft', 'status' => 'DRAFT']);
        $fields = [
            'name' => 'Arcilla editorial', 'slug' => 'arcilla-editorial',
            'headline' => 'DEJA EL SCROLL. ENSÚCIATE LAS MANOS.',
            'subheadline' => 'Cambia la pantalla por arcilla. Dale forma a una pieza tuya, sin prisas y con alguien que te guíe.',
            'reward_display_override' => 'No se trata de hacer una pieza perfecta. Se trata de sentarte, tocar la arcilla y ver cómo algo empieza a tomar forma entre tus manos. Materiales incluidos.',
            'final_cta_description' => 'Una tarde para cambiar el scroll por formas, texturas y una pieza hecha por ti. Propuesta demostrativa.',
        ];
        $this->put($base.'/'.$landing->slug, $fields)->assertRedirect($base)->assertSessionHasNoErrors();
        foreach ($fields as $key => $value) $this->assertSame($value, $landing->fresh()->$key);
        $this->assertSame($domain, $subject->fresh()->getAttributes());
        $this->get($base.'/arcilla-editorial/preview')->assertInertia(fn (Assert $page) => $page
            ->where('offerEditorial.subheadline', $fields['subheadline'])
            ->where('copy.valueNote', $fields['reward_display_override'])
            ->where('offerEditorial.finalDescription', $fields['final_cta_description']));
    }

    public function test_real_editorial_limits_and_structural_security_remain_specific(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $subject = Experience::factory()->published()->create();
        $base = '/admin/experiencias/'.$subject->slug.'/landings';
        $fields = ['name' => 'Arcilla', 'slug' => 'arcilla'];
        $this->post($base, [...$fields, 'reward_display_override' => str_repeat('a', 181)])
            ->assertSessionHasErrors(['reward_display_override' => 'Copy complementario del valor no debe superar 180 caracteres.']);
        foreach ([['headline' => '<b>Arcilla</b>'], ['template_key' => 'CUSTOM'], ['marketing_theme' => '#fff'], ['cta_url' => 'https://example.test'], ['action' => 'other'], ['prices' => ['regular_price' => 1]], ['headline' => 'Arcilla', 'headline_emphasis' => 'Missing']] as $invalid) {
            $this->post($base, [...$fields, ...$invalid])->assertSessionHasErrors(array_keys($invalid)[0] === 'headline' && isset($invalid['headline_emphasis']) ? 'headline_emphasis' : array_keys($invalid)[0]);
        }
        $this->assertSame(0, LandingPresentation::count());
    }
}
