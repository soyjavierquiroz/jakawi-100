<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\LandingPresentation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketingLandingAdminV2Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function landing($subject, array $attributes = []): LandingPresentation
    {
        return $subject->landingPresentations()->create($attributes + ['name' => 'Landing editorial', 'slug' => 'landing-'.fake()->uuid(), 'status' => 'DRAFT']);
    }

    public function test_catalog_searches_real_relations_without_duplicate_landings_or_partners(): void
    {
        $this->admin();
        $a = Partner::factory()->create(['name' => 'Partner Aguamarina']);
        $b = Partner::factory()->create(['name' => 'Partner Cedro']);
        $benefit = Benefit::factory()->forPartner($a)->create(['title' => 'Producto Colibrí']);
        $experience = Experience::factory()->create(['title' => 'Producto Horizonte']);
        $experience->syncPartnersWithRoles([
            ['partner_id' => $a->id, 'role' => 'organizer'], ['partner_id' => $a->id, 'role' => 'venue'], ['partner_id' => $b->id, 'role' => 'venue'],
        ]);
        $one = $this->landing($benefit, ['name' => 'Título % literal', 'status' => 'PUBLISHED']);
        $two = $this->landing($experience, ['status' => 'ARCHIVED']);
        $this->landing(Experience::factory()->create());
        $this->get('/admin/landings?q=aguamarina')->assertInertia(fn (Assert $p) => $p->component('admin/landing-presentations/catalog')->has('landings', 2)->where('pagination.total', 2));
        $this->get('/admin/landings?q=cedro')->assertInertia(fn (Assert $p) => $p->has('landings', 1)->where('landings.0.id', $two->id)->has('landings.0.partners', 2));
        foreach (['Colibrí', 'Título', '%'] as $q) {
            $this->get('/admin/landings?q='.urlencode($q))->assertInertia(fn (Assert $p) => $p->has('landings', 1)->where('landings.0.id', $one->id));
        }
        $this->get('/admin/landings?partner='.$a->id.'&type=EXPERIENCE&status=ARCHIVED&video=no&q=Horizonte')->assertInertia(fn (Assert $p) => $p->has('landings', 1)->where('landings.0.id', $two->id));
        $this->get('/admin/landings?q=missing')->assertInertia(fn (Assert $p) => $p->has('landings', 0)->where('pagination.total', 0));
    }

    public function test_video_filters_match_render_composition_and_reject_missing_invalid_or_unsupported_sources(): void
    {
        $this->admin();
        $subject = Benefit::factory()->create();
        $valid = [
            ['youtube', 'https://youtu.be/hI-Ecy2D8pg'],
            ['youtube', 'https://www.youtube.com/watch?v=hI-Ecy2D8pg&t=4'],
            ['youtube', 'https://m.youtube.com/watch?feature=share&v=hI-Ecy2D8pg'],
            ['youtube', 'https://www.youtube-nocookie.com/embed/hI-Ecy2D8pg'],
            ['youtube', 'https://youtube.com/shorts/hI-Ecy2D8pg'],
            ['mp4', 'https://cdn.example.test/film.mp4?signature=123'],
            ['bunny', 'https://cdn.example.test/playlist.m3u8'],
        ];
        foreach ($valid as [$type, $url]) {
            $this->landing($subject, ['vsl' => ['source' => compact('type', 'url')], 'media_mode' => 'VSL']);
        }
        foreach ([['youtube', 'https://example.test/watch?v=hI-Ecy2D8pg'], ['youtube', 'https://youtu.be/short'], ['youtube', 'https://youtu.be/hI-Ecy2D8pg/extra'], ['mp4', 'javascript:alert(1)'], ['mp4', 'https://user:password@cdn.example.test/a.mp4'], ['mp4', 'https://cdn.example.test/a bad.mp4'], ['vimeo', 'https://example.test/v'], ['mp4', '']] as [$type, $url]) {
            $this->landing($subject, ['vsl' => ['source' => compact('type', 'url')], 'media_mode' => 'VSL']);
        }
        $this->landing($subject);
        $this->landing($subject, ['vsl' => ['source' => ['type' => 'mp4', 'url' => 'https://cdn.example.test/a.mp4']], 'media_mode' => 'IMAGE']);
        $this->get('/admin/landings?video=yes')->assertInertia(fn (Assert $p) => $p->has('landings', 7)->where('pagination.total', 7)->where('landings.0.has_video', true)->has('landings.0.video.source'));
        $this->get('/admin/landings?video=no')->assertInertia(fn (Assert $p) => $p->has('landings', 10)->where('pagination.total', 10)->where('landings.0.has_video', false)->where('landings.0.video', null));
    }

    public function test_pagination_is_bounded_stable_preserves_filters_and_has_bounded_queries(): void
    {
        $this->admin();
        $subject = Experience::factory()->create();
        $subject->partners()->attach(Partner::factory()->create(), ['role' => 'organizer']);
        $ids = [];
        for ($i = 0; $i < 28; $i++) {
            $ids[] = $this->landing($subject, ['name' => 'Lote editorial', 'updated_at' => '2026-10-01 12:00:00'])->id;
        }
        DB::enableQueryLog();
        $this->get('/admin/landings?q=Lote&type=EXPERIENCE&status=DRAFT&video=no&per_page=100')->assertInertia(fn (Assert $p) => $p->has('landings', 25)->where('pagination.total', 28)->where('landings.0.id', $ids[27])->where('pagination.next_page_url', fn ($url) => str_contains($url, 'q=Lote') && str_contains($url, 'type=EXPERIENCE') && str_contains($url, 'video=no') && str_contains($url, 'page=2')));
        $selects = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'select'));
        $this->assertLessThan(16, $selects->count());
        DB::disableQueryLog();
        $this->get('/admin/landings?q=Lote&page=2')->assertInertia(fn (Assert $p) => $p->has('landings', 3)->where('landings.0.id', $ids[2]));
        $this->get('/admin/landings?page=999')->assertInertia(fn (Assert $p) => $p->where('pagination.current_page', 2)->has('landings', 3));
    }

    public function test_direct_edit_and_preview_keep_publication_and_private_content_protected(): void
    {
        $subject = Benefit::factory()->published()->forPartner(Partner::factory()->published()->create())->create();
        $draft = $this->landing($subject, ['media_mode' => 'VSL', 'vsl' => ['source' => ['type' => 'mp4', 'url' => 'https://cdn.example.test/real.mp4']]]);
        $archived = $this->landing($subject, ['status' => 'ARCHIVED']);
        $published = $this->landing($subject, ['status' => 'PUBLISHED']);
        $base = '/admin/beneficios/'.$subject->slug.'/landings';
        $this->get('/admin/landings/'.$draft->slug.'/edit')->assertRedirect('/login');
        $this->get($base.'/'.$draft->slug.'/preview')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        foreach (['/admin/landings', '/admin/landings/partners', '/admin/landings/'.$draft->slug.'/edit', $base.'/'.$draft->slug.'/preview', $base.'/'.$archived->slug.'/preview'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->admin();
        $before = $draft->fresh()->getAttributes();
        $this->get('/admin/landings/'.$draft->slug.'/edit')->assertInertia(fn (Assert $p) => $p->component('admin/landing-presentations/index')->where('subject.id', $subject->id)->where('autoEdit.id', $draft->id)->where('baseUrl', $base));
        $this->get('/admin/landings/new')->assertInertia(fn (Assert $p) => $p->component('admin/landing-presentations/index')->where('subject', null)->has('subjectChoices.BENEFIT'));
        $this->get($base.'/'.$draft->slug.'/preview')->assertInertia(fn (Assert $p) => $p->component('landing-presentations/benefit')->where('preview', true)->where('noindex', true)->where('vsl.source.url', 'https://cdn.example.test/real.mp4'));
        $this->get($base.'/'.$archived->slug.'/preview')->assertOk();
        $this->get('/admin/landings?status=PUBLISHED')->assertInertia(fn (Assert $p) => $p->where('landings.0.preview_url', '/l/'.$published->slug));
        $this->assertSame($before, $draft->fresh()->getAttributes());
        $this->get('/l/'.$draft->slug)->assertNotFound();
        $this->get('/l/'.$archived->slug)->assertNotFound();
        $other = Benefit::factory()->create();
        $this->get('/admin/beneficios/'.$other->slug.'/landings/'.$draft->slug.'/preview')->assertNotFound();
    }

    public function test_missing_associations_and_partner_lookup_use_real_data(): void
    {
        $this->admin();
        $subject = Experience::factory()->create();
        $this->landing($subject);
        LandingPresentation::create(['name' => 'Asociación pendiente', 'slug' => 'orphan', 'status' => 'DRAFT', 'subject_type' => Benefit::class, 'subject_id' => 999999]);
        $this->get('/admin/landings')->assertInertia(fn (Assert $p) => $p->has('landings', 2)->where('landings.0.product', null)->where('landings.0.partners', [])->where('landings.0.preview_url', null)->where('landings.1.partners', []));
        $partner = Partner::factory()->create(['name' => 'Búsqueda real']);
        $this->getJson('/admin/landings/partners?q=real')->assertOk()->assertExactJson([['id' => $partner->id, 'name' => $partner->name]]);
        $this->getJson('/admin/landings?type=FAKE')->assertUnprocessable();
        $this->getJson('/admin/landings?partner=999999')->assertUnprocessable();
    }
}
