<?php

namespace Tests\Feature;

use App\Enums\LandingMediaMode;
use App\Enums\LandingTemplate;
use App\Enums\MarketingTheme;
use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Partner;
use App\Models\Unlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketingLandingAdminV1Test extends TestCase
{
    use RefreshDatabase;

    private function subject(): Benefit
    {
        return Benefit::factory()->published()->forPartner(Partner::factory()->published()->create())->create(['title' => 'Café de cortesía']);
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function base(Benefit $subject): string
    {
        return '/admin/beneficios/'.$subject->slug.'/landings';
    }

    private function fields(array $overrides = []): array
    {
        return array_replace(['name' => 'Oferta editorial', 'slug' => 'oferta-editorial', 'template_key' => 'OFFER_CONVERSION', 'marketing_theme' => 'LIGHT_CONVERSION', 'media_mode' => 'NONE', 'headline' => 'TU CAFÉ DE CORTESÍA.', 'headline_emphasis' => 'CAFÉ DE CORTESÍA.', 'eyebrow' => 'BENEFICIO JAKAWI', 'subheadline' => 'Disfruta tu próximo plan.', 'final_cta_headline' => 'PREPARA TU VISITA.', 'final_cta_description' => 'Continúa con JAKAWI.', 'campaign_key' => 'cafe-piloto', 'default_scope' => 'GUESTS'], $overrides);
    }

    public function test_admin_saves_only_two_acquisition_copy_values_and_preview_projects_them(): void
    {
        $subject = $this->subject();
        $this->admin();
        $this->post($this->base($subject), $this->fields([
            'acquisition_headline' => 'Tu café está a un paso',
            'acquisition_supporting_copy' => 'Dinos quién eres y seguimos exactamente aquí.',
        ]))->assertRedirect($this->base($subject));
        $landing = LandingPresentation::where('slug', 'oferta-editorial')->sole();
        $this->get($this->base($subject).'/'.$landing->slug.'/preview')->assertInertia(fn (Assert $page) => $page
            ->where('acquisition.headline', 'Tu café está a un paso')
            ->where('acquisition.supportingCopy', 'Dinos quién eres y seguimos exactamente aquí.'));
        $this->put($this->base($subject).'/'.$landing->slug, $this->fields(['acquisition_headline' => '<script>unsafe</script>']))->assertSessionHasErrors('acquisition_headline');
    }

    public function test_saved_editorial_is_rendered_verbatim_and_layout_controls_are_validated(): void
    {
        $subject = $this->subject();
        $this->admin();
        $base = $this->base($subject);
        $this->post($base, $this->fields(['headline' => 'DISFRUTA EN TU CIUDAD.', 'headline_emphasis' => 'EN TU CIUDAD', 'hero_layout' => 'MEDIA_LED', 'density' => 'COMPACT']))->assertRedirect($base);
        $landing = LandingPresentation::where('slug', 'oferta-editorial')->firstOrFail();
        $this->get($base.'/'.$landing->slug.'/preview')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('copy.headline', 'DISFRUTA EN TU CIUDAD.')
            ->where('offerEditorial.headline', 'DISFRUTA EN TU CIUDAD.')
            ->where('presentation.hero_layout', 'MEDIA_LED')->where('presentation.density', 'COMPACT'));
        $this->put($base.'/'.$landing->slug, $this->fields(['hero_layout' => 'INVALID', 'density' => 'INVALID']))->assertSessionHasErrors(['hero_layout', 'density']);
    }

    public function test_experience_editorial_containing_en_is_not_silently_replaced(): void
    {
        $subject = Experience::factory()->published()->create();
        $this->admin();
        $base = '/admin/experiencias/'.$subject->slug.'/landings';
        $this->post($base, $this->fields(['headline' => 'VIVE EL ATARDECER EN COCHABAMBA.', 'headline_emphasis' => 'ATARDECER', 'subheadline' => 'Música suave y vista a la ciudad en Mirador Cobalto.']))->assertRedirect($base);
        $landing = LandingPresentation::where('slug', 'oferta-editorial')->firstOrFail();
        $this->get($base.'/'.$landing->slug.'/preview')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('copy.headline', 'VIVE EL ATARDECER EN COCHABAMBA.')
            ->where('offerEditorial.headline', 'VIVE EL ATARDECER EN COCHABAMBA.')
            ->where('offerEditorial.subheadline', 'Música suave y vista a la ciudad en Mirador Cobalto.')
            ->where('offerEditorial.emphasis', 'ATARDECER'));
    }

    public function test_emphasis_uses_effective_product_headline_when_editorial_headline_is_empty(): void
    {
        $subject = $this->subject();
        $this->admin();
        $this->post($this->base($subject), $this->fields(['headline' => null, 'headline_emphasis' => 'Café']))->assertRedirect($this->base($subject));
        $landing = LandingPresentation::where('slug', 'oferta-editorial')->firstOrFail();
        $this->put($this->base($subject).'/'.$landing->slug, $this->fields(['headline' => null, 'headline_emphasis' => 'Ausente']))->assertSessionHasErrors('headline_emphasis');
    }

    public function test_create_edit_publish_unpublish_and_live_domain_truth(): void
    {
        $subject = $this->subject();
        $original = $subject->fresh()->getAttributes();
        $this->admin();
        $base = $this->base($subject);
        $this->post($base, $this->fields())->assertRedirect($base);
        $landing = LandingPresentation::where('slug', 'oferta-editorial')->firstOrFail();
        $this->assertSame('DRAFT', $landing->status);
        $this->assertSame('NONE', $landing->default_scope);
        $this->assertSame('GUESTS', $landing->publish_scope);
        $this->assertSame(LandingTemplate::OFFER_CONVERSION, $landing->template_key);
        $this->assertSame(MarketingTheme::LIGHT_CONVERSION, $landing->marketing_theme);
        $this->assertSame(LandingMediaMode::NONE, $landing->media_mode);
        $this->assertSame(1, $landing->editorial_version);
        $this->assertSame($original, $subject->fresh()->getAttributes());
        $this->get('/l/'.$landing->slug)->assertNotFound();
        $this->put($base.'/'.$landing->slug, $this->fields(['marketing_theme' => 'DARK_CONVERSION', 'final_cta_description' => 'Descubre tu próximo plan.']))->assertRedirect();
        $this->post($base.'/'.$landing->slug.'/publish')->assertRedirect();
        $this->assertSame('GUESTS', $landing->fresh()->default_scope);
        $this->get('/l/'.$landing->slug.'?campaign_key=injected')->assertOk()->assertInertia(fn (Assert $page) => $page->component('landing-presentations/benefit')->where('presentation.template_key', 'OFFER_CONVERSION')->where('presentation.marketing_theme', 'DARK_CONVERSION')->where('presentation.campaign_key', 'cafe-piloto')->where('offerEditorial.headline', 'TU CAFÉ DE CORTESÍA.')->where('offerEditorial.emphasis', 'CAFÉ DE CORTESÍA.')->where('offerEditorial.finalDescription', 'Descubre tu próximo plan.')->where('heroUrl', null)->where('vsl', null));
        $this->post($base.'/'.$landing->slug.'/unpublish')->assertRedirect();
        $this->assertSame('DRAFT', $landing->fresh()->status);
        $this->assertSame('NONE', $landing->fresh()->default_scope);
        $this->assertSame('GUESTS', $landing->fresh()->publish_scope);
        $this->get('/l/'.$landing->slug)->assertNotFound();
    }

    public function test_editorial_save_preserves_selected_identity_publication_and_sibling(): void
    {
        $subject = $this->subject();
        $this->admin();
        $base = $this->base($subject);
        $this->post($base, $this->fields(['default_scope' => 'ALL']))->assertRedirect();
        $published = LandingPresentation::where('slug', 'oferta-editorial')->sole();
        $this->post($base.'/'.$published->slug.'/publish')->assertRedirect();
        $this->post($base, $this->fields(['slug' => 'sibling-draft', 'default_scope' => 'NONE']))->assertRedirect();
        $draft = LandingPresentation::where('slug', 'sibling-draft')->sole();
        $protected = ['id', 'subject_type', 'subject_id', 'slug', 'default_scope', 'publish_scope', 'status', 'published_at'];
        foreach ([$published, $draft] as $selected) {
            $before = $selected->fresh()->only($protected);
            $sibling = $selected->id === $published->id ? $draft : $published;
            $siblingBefore = $sibling->fresh()->getAttributes();
            $this->put($base.'/'.$selected->slug, $this->fields([
                'slug' => $selected->slug,
                'default_scope' => $selected->fresh()->publish_scope,
                'subject_type' => 'BENEFIT', 'subject_id' => $subject->id,
                'subheadline' => 'Copy importado y guardado explícitamente.',
            ]))->assertRedirect($base);
            $this->assertSame($before, $selected->fresh()->only($protected));
            $this->assertSame($siblingBefore, $sibling->fresh()->getAttributes());
            $this->assertSame('Copy importado y guardado explícitamente.', $selected->fresh()->subheadline);
            $this->assertSame(2, LandingPresentation::count());
        }
    }

    public function test_allowlists_plain_text_emphasis_subject_and_no_action_editor(): void
    {
        $subject = $this->subject();
        $this->admin();
        $base = $this->base($subject);
        foreach ([['template_key' => 'CUSTOM'], ['marketing_theme' => '#ff0000'], ['media_mode' => 'HTML'], ['headline_emphasis' => 'NO EXISTE'], ['headline' => '<b>Oferta</b>'], ['headline_emphasis' => '**CAFÉ**'], ['final_cta_description' => '<script>bad</script>'], ['cta_url' => 'https://attacker.example'], ['cta_payload' => ['location_id' => 123]], ['prices' => [100]], ['subject_type' => 'UNLOCK', 'subject_id' => 1], ['subject_type' => 'BENEFIT', 'subject_id' => 999999], ['media_mode' => 'VSL', 'vsl' => null]] as $invalid) {
            $this->post($base, $this->fields($invalid))->assertSessionHasErrors();
            $this->assertSame(0, LandingPresentation::count());
        }
        $this->post($base, $this->fields())->assertRedirect();
        $this->post($base, $this->fields())->assertSessionHasErrors('slug');
    }

    public function test_media_switching_keeps_stored_config_and_duplicate_is_new_draft(): void
    {
        $subject = $this->subject();
        $this->admin();
        $base = $this->base($subject);
        $video = ['source' => ['type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=hI-Ecy2D8pg'], 'framing' => 'embedded-vertical', 'mode' => 'vsl'];
        $subject->update(['image_path' => 'images/pilot.jpg']);
        $fields = $this->fields(['media_mode' => 'VSL', 'hero_path' => $subject->image_path, 'vsl' => $video]);
        $this->post($base, $fields)->assertRedirect();
        $landing = LandingPresentation::firstOrFail();
        foreach (['IMAGE', 'NONE', 'VSL'] as $mode) {
            $this->put($base.'/'.$landing->slug, array_replace($fields, ['media_mode' => $mode]))->assertRedirect();
            $this->assertSame($video, $landing->fresh()->vsl);
            $this->assertSame('images/pilot.jpg', $landing->fresh()->hero_path);
        }
        $this->post($base.'/'.$landing->slug.'/publish')->assertRedirect();
        $count = Benefit::count();
        $this->post($base.'/'.$landing->slug.'/duplicate', ['name' => 'Campaña QR', 'slug' => 'campana-qr', 'campaign_key' => 'qr-cafe'])->assertRedirect();
        $clone = LandingPresentation::where('slug', 'campana-qr')->firstOrFail();
        $this->assertSame('DRAFT', $clone->status);
        $this->assertSame('NONE', $clone->default_scope);
        $this->assertSame('GUESTS', $clone->publish_scope);
        $this->assertSame('qr-cafe', $clone->campaign_key);
        foreach (['subject_type', 'subject_id', 'template_key', 'marketing_theme', 'headline', 'headline_emphasis', 'hero_path', 'vsl', 'final_cta_description'] as $key) {
            $this->assertEquals($landing->fresh()->$key, $clone->$key);
        }
        $this->assertSame($count, Benefit::count());
        $this->post($base.'/'.$landing->slug.'/duplicate', ['name' => 'Invalid', 'slug' => 'campana-qr'])->assertSessionHasErrors('slug');
        $this->put($base.'/'.$landing->slug, array_replace($fields, ['vsl' => null]))->assertSessionHasErrors('vsl');
        $this->assertSame($video, $landing->fresh()->vsl);
    }

    public function test_existing_admin_subject_picker_summary_and_reassignment(): void
    {
        $subject = $this->subject();
        $experience = Experience::factory()->published()->create();
        $this->admin();
        $this->get('/admin/landings')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/landing-presentations/index')->where('subject', null)->has('subjectChoices.BENEFIT', 1)->has('subjectChoices.EXPERIENCE', 1)->missing('subjectChoices.UNLOCK'));
        $this->get('/admin/landings?subject_type=BENEFIT&subject_id='.$subject->id.'&create=1')->assertOk()->assertInertia(fn (Assert $page) => $page->where('subject.type', 'BENEFIT')->where('subject.defaults.headline', $subject->title)->has('subject.truth', 5)->where('autoCreate', true));
        $this->get('/admin/landings/subject?subject_type=EXPERIENCE&subject_id='.$experience->id)->assertOk()->assertJsonPath('type', 'EXPERIENCE')->assertJsonPath('id', $experience->id);
        $base = $this->base($subject);
        $this->post($base, $this->fields())->assertRedirect();
        $this->put($base.'/oferta-editorial', $this->fields(['subject_type' => 'EXPERIENCE', 'subject_id' => $experience->id]))->assertRedirect('/admin/experiencias/'.$experience->slug.'/landings');
        $landing = LandingPresentation::firstOrFail();
        $this->assertSame($experience->getMorphClass(), $landing->subject_type);
        $this->assertSame($experience->id, $landing->subject_id);
        $this->get($base.'/'.$landing->slug.'/preview')->assertNotFound();
    }

    public function test_preview_is_authorized_noindex_and_has_no_business_or_growth_writes(): void
    {
        $subject = $this->subject();
        $this->admin();
        $base = $this->base($subject);
        $this->post($base, $this->fields())->assertRedirect();
        $landing = LandingPresentation::firstOrFail();
        $tables = ['benefit_redemptions', 'growth_events', 'campaign_conversions'];
        $before = [];
        foreach ($tables as $table) {
            if (\Schema::hasTable($table)) {
                $before[$table] = DB::table($table)->count();
            }
        }
        $this->get($base.'/'.$landing->slug.'/preview?campaign_key=forged')->assertOk()->assertInertia(fn (Assert $page) => $page->component('landing-presentations/benefit')->where('preview', true)->where('noindex', true)->where('offerEditorial.version', 1));
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
        $this->actingAs(User::factory()->create());
        $this->get('/admin/landings')->assertForbidden();
        $this->get('/admin/landings/subject?subject_type=BENEFIT&subject_id='.$subject->id)->assertForbidden();
        $this->get($base.'/'.$landing->slug.'/preview')->assertForbidden();
        $this->post($base, $this->fields(['slug' => 'forbidden']))->assertForbidden();
        $this->put($base.'/'.$landing->slug, $this->fields())->assertForbidden();
        foreach (['duplicate', 'publish', 'unpublish', 'archive'] as $action) {
            $this->post($base.'/'.$landing->slug.'/'.$action, ['name' => 'bad', 'slug' => 'bad-copy'])->assertForbidden();
        }
        $this->assertSame('DRAFT', $landing->fresh()->status);
        $this->assertSame(1, LandingPresentation::count());
    }

    public function test_unlock_editorial_admin_keeps_legacy_validation_without_offer_settings(): void
    {
        $unlock = Unlock::create(['title'=>'Private unlock', 'slug'=>'legacy-admin-unlock', 'origin'=>'JAKAWI', 'type'=>'BENEFIT', 'minimum_commitments'=>2, 'status'=>Unlock::ACTIVE]);
        $this->admin();
        $base = '/admin/desbloqueos/'.$unlock->slug.'/landings';
        $this->post($base, ['name'=>'Legacy', 'slug'=>'unlock-legacy-editor', 'headline'=>'Mensaje legacy 2026'])->assertRedirect();
        $landing = LandingPresentation::where('slug', 'unlock-legacy-editor')->firstOrFail();
        $this->assertSame('Mensaje legacy 2026', $landing->headline);
        $this->assertNull($landing->template_key);
        $this->assertNull($landing->marketing_theme);
        $this->post($base, ['name'=>'Invalid offer', 'slug'=>'unlock-offer', 'template_key'=>'OFFER_CONVERSION'])->assertSessionHasErrors('template_key');
    }

    public function test_migration_is_deterministic_reversible_preserves_editorial_and_unlock(): void
    {
        $benefit = $this->subject();
        $experience = Experience::factory()->published()->create();
        $challenge = Challenge::create(['title' => 'Reto', 'slug' => 'migration-reto', 'status' => 'open', 'review_status' => 'APPROVED', 'description' => 'Reto', 'evidence_type' => 'SOCIAL_POST', 'qualification_type' => 'VALID_EVIDENCE', 'selection_type' => 'ALL_QUALIFIED', 'evaluation_mode' => 'CONTINUOUS', 'review_mode' => 'AUTOMATIC', 'participation_eligibility' => 'ALL_USERS', 'reward_eligibility' => 'ALL_USERS', 'reward_type' => 'JP', 'reward_jp_amount' => 25]);
        $unlock = Unlock::create(['title' => 'Private', 'slug' => 'migration-unlock', 'origin' => 'JAKAWI', 'type' => 'BENEFIT', 'minimum_commitments' => 2, 'status' => Unlock::ACTIVE]);
        $video = ['source' => ['type' => 'bunny', 'url' => 'https://cdn.example/playlist.m3u8']];
        $ids = [];
        foreach ([$challenge, $experience, $benefit, $unlock] as $i => $subject) {
            $ids[] = $subject->landingPresentations()->create(['name' => 'Legacy', 'slug' => 'legacy-'.$i, 'status' => 'PUBLISHED', 'default_scope' => 'ALL', 'headline' => 'MENSAJE APROBADO', 'hero_path' => 'legacy.jpg', 'vsl' => $i === 0 ? $video : null])->id;
        }
        $migration = require database_path('migrations/2026_10_12_000001_add_offer_presentation_settings.php');
        $migration->down();
        $migration->up();
        foreach ($ids as $i => $id) {
            $landing = LandingPresentation::findOrFail($id);
            $this->assertSame('MENSAJE APROBADO', $landing->headline);
            $this->assertSame('legacy.jpg', $landing->hero_path);
            $this->assertSame('ALL', $landing->publish_scope);
            $this->assertSame(0, $landing->editorial_version);
            if ($i === 3) {
                $this->assertNull($landing->template_key);
                $this->assertNull($landing->marketing_theme);
            } else {
                $this->assertSame(LandingTemplate::OFFER_CONVERSION, $landing->template_key);
                $this->assertSame($i === 0 ? MarketingTheme::DARK_CONVERSION : MarketingTheme::LIGHT_CONVERSION,$landing->marketing_theme);
                $this->assertSame($i === 0 ? LandingMediaMode::VSL : LandingMediaMode::IMAGE,$landing->media_mode);
            }
        }
        $this->assertSame($video,LandingPresentation::find($ids[0])->vsl);
    }
}
