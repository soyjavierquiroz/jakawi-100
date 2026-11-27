<?php

namespace Tests\Feature;
use App\Models\{DigitalProduct, LandingPresentation, Partner, User};
use App\Services\{GluteoPerfectoDraft, ProductLandingResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalLandingTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): array
    {
        $result = app(GluteoPerfectoDraft::class)->create(false);
        return [DigitalProduct::findOrFail($result['product_id']), LandingPresentation::findOrFail($result['landing_id'])];
    }

    public function test_draft_creation_is_idempotent_preserves_editorial_changes_and_has_no_economics(): void
    {
        [$p, $l] = $this->draft();
        $p->update(['description' => 'Revisión del propietario']);
        $again = app(GluteoPerfectoDraft::class)->create(false);
        $this->assertEquals($p->id, $again['product_id']);
        $this->assertSame(1, DigitalProduct::count());
        $this->assertSame(1, LandingPresentation::count());
        $this->assertSame('Revisión del propietario', $p->fresh()->description);
        $this->assertSame('fitness', $p->category);
        $this->assertNull($p->public_price);
        $this->assertNull($p->file_path);
        $this->assertSame('draft', $p->status);
        $this->assertSame('DRAFT', $l->status);
        $this->assertCount(11, $l->editorial_blocks);
        $this->assertCount(3, collect($l->editorial_blocks)->firstWhere('type','testimonials')['items']);
        $this->assertCount(5, collect($l->editorial_blocks)->firstWhere('type','faq')['items']);
        foreach (['digital_orders','digital_payments','digital_access_grants'] as $table) $this->assertDatabaseCount($table,0);
    }

    public function test_private_previews_and_editor_resolve_the_same_product_and_never_publish_a_draft(): void
    {
        [$p, $l] = $this->draft();
        $this->get('/l/gluteo-perfecto')->assertNotFound();
        $this->get('/productos-digitales/gluteo-perfecto')->assertNotFound();
        $this->get('/explorar?type=digital&q=Glúteo')->assertInertia(fn (Assert $a) => $a->has('results',0));
        $preview = '/admin/productos-digitales/gluteo-perfecto/landings/gluteo-perfecto/preview';
        $this->get($preview)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($preview)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->get($preview)->assertOk()->assertInertia(fn (Assert $a) => $a->component('landing-presentations/digital')->where('product.id',$p->id)->where('canBuy',false)->where('preview',true)->has('blocks',11)->missing('product.file_path'));
        $this->get('/admin/productos-digitales/gluteo-perfecto/preview')->assertOk()->assertInertia(fn (Assert $a) => $a->component('digital/show')->where('preview',true)->where('canCollect',false));
        $this->get('/admin/landings/gluteo-perfecto/edit')->assertOk()->assertInertia(fn (Assert $a) => $a->where('subject.type','DIGITAL')->where('subject.title','Glúteo Perfecto')->has('autoEdit.editorial_blocks',11));
        $this->get('/admin/landings?type=DIGITAL&q=Glúteo')->assertOk()->assertInertia(fn (Assert $a) => $a->has('landings',1)->where('landings.0.product.type','DIGITAL'));
        $this->post('/admin/productos-digitales/gluteo-perfecto/landings/gluteo-perfecto/publish')->assertSessionHasErrors('presentation');
        $this->assertSame('DRAFT',$l->fresh()->status);
    }

    public function test_editor_saves_and_duplicates_all_sections_without_accepting_prices_urls_or_private_images(): void
    {
        [$p,$l] = $this->draft();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $url='/admin/productos-digitales/gluteo-perfecto/landings/gluteo-perfecto';
        $blocks=$l->editorial_blocks;
        $blocks[2]['title']='Texto revisado';
        $this->put($url,['name'=>$l->name,'slug'=>$l->slug,'editorial_blocks'=>$blocks])->assertRedirect();
        $this->assertSame('Texto revisado',$l->fresh()->editorial_blocks[2]['title']);
        $blocks[10]['items']=[['label'=>'Privacidad QA','href'=>'/privacidad-qa']];
        $this->put($url,['name'=>$l->name,'slug'=>$l->slug,'editorial_blocks'=>$blocks])->assertRedirect();
        $blocks[10]['items'][0]['href']='javascript:alert(1)';
        $this->put($url,['name'=>$l->name,'slug'=>$l->slug,'editorial_blocks'=>$blocks])->assertSessionHasErrors('blocks.10.items.0.href');
        $blocks[10]['items']=[];
        $blocks[6]['price']='12.90';
        $this->put($url,['name'=>$l->name,'slug'=>$l->slug,'editorial_blocks'=>$blocks])->assertSessionHasErrors('blocks.6');
        unset($blocks[6]['price']);
        $blocks[5]['items'][0]['image_path']='membership-private/statement.pdf';
        $this->put($url,['name'=>$l->name,'slug'=>$l->slug,'editorial_blocks'=>$blocks])->assertSessionHasErrors('editorial_blocks.5.items.0.image_path');
        $this->post($url.'/duplicate',['name'=>'Otra composición','slug'=>'otra-composicion'])->assertRedirect();
        $clone=LandingPresentation::where('slug','otra-composicion')->sole();
        $this->assertEquals($l->fresh()->editorial_blocks,$clone->editorial_blocks);
        $this->assertEquals($p->id,$clone->subject_id);
        $this->assertSame('DRAFT',$clone->status);
    }

    public function test_visual_presets_survive_save_publish_and_preview_without_changing_copy_or_commerce(): void
    {
        [$p, $l] = $this->draft();
        $p->update(['status' => 'published']);
        $initial = $l->editorial_blocks;
        $initial[0]['availability'] = 'COMING_SOON';
        $l->update(['status' => 'PUBLISHED', 'editorial_blocks' => $initial]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $blocks = $l->editorial_blocks;
        $blocks[2] += ['layout' => 'flow', 'title_emphasis' => 'entrenamiento', 'background' => 'dark', 'variant' => 'minimal', 'image_position' => 'left', 'image_alt' => 'Entrenamiento'];
        $url = '/admin/productos-digitales/gluteo-perfecto/landings/gluteo-perfecto';
        $this->put($url, ['name' => $l->name, 'slug' => $l->slug, 'editorial_blocks' => $blocks])->assertRedirect()->assertSessionHasNoErrors();
        $saved = $l->fresh()->editorial_blocks[2];
        $this->assertSame('dark', $saved['background']);
        $this->assertSame('minimal', $saved['variant']);
        $this->assertSame('flow', $saved['layout']);
        $this->assertSame('entrenamiento', $saved['title_emphasis']);
        $this->assertSame('left', $saved['image_position']);
        $this->assertSame($blocks[2]['subtitle'], $saved['subtitle']);
        $this->post($url.'/publish', ['publish_scope' => 'ALL'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/l/gluteo-perfecto')->assertOk()->assertInertia(fn (Assert $a) => $a->where('blocks.2.layout', 'flow')->where('blocks.2.background', 'dark')->where('canBuy', false));
        $blocks[2]['background'] = '#badcss';
        $this->put($url, ['name' => $l->name, 'slug' => $l->slug, 'editorial_blocks' => $blocks])->assertSessionHasErrors('blocks.2.background');
        $blocks[2]['background'] = 'light';
        $blocks[2]['image_path'] = 'digital-private/secret.pdf';
        $this->put($url, ['name' => $l->name, 'slug' => $l->slug, 'editorial_blocks' => $blocks])->assertSessionHasErrors('editorial_blocks.2.image_path');
        \Illuminate\Support\Facades\Storage::fake(config('media.disk'));
        $blocks = $l->fresh()->editorial_blocks;
        $blocks[2]['image_upload'] = app(\App\Services\MediaUploadService::class)->temporary(\Illuminate\Http\UploadedFile::fake()->image('editorial.png', 800, 600), auth()->id());
        $this->put($url, ['name'=>$l->name, 'slug'=>$l->slug, 'editorial_blocks'=>$blocks])->assertRedirect()->assertSessionHasNoErrors();
        $blocks = $l->fresh()->editorial_blocks;
        $this->assertStringStartsWith('landings/', $blocks[2]['image_path']);
        $blocks[2]['image_remove'] = true;
        $this->put($url, ['name'=>$l->name, 'slug'=>$l->slug, 'editorial_blocks'=>$blocks])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($l->fresh()->editorial_blocks[2]['image_path']);
        $this->assertDatabaseCount('digital_orders', 0);
    }

    public function test_published_projection_has_one_price_and_requires_commercial_authorization(): void
    {
        [$p,$l]=$this->draft();
        $p->update(['status'=>'published','public_price'=>'40.00','member_price'=>'30.00']);
        $l->update(['status'=>'PUBLISHED','default_scope'=>'ALL']);
        config(['jakawi.prelaunch_mode'=>true]);
        $this->get('/l/gluteo-perfecto')->assertOk()->assertInertia(fn (Assert $a) => $a->where('product.public_price','40.00')->where('product.member_price','30.00')->where('canBuy',false)->where('checkoutUrl','/productos-digitales/gluteo-perfecto/comprar')->has('blocks',8));
        $this->get('/productos-digitales/gluteo-perfecto')->assertInertia(fn (Assert $a) => $a->where('product.public_price','40.00'));
        $this->get('/explorar?type=digital&q=Glúteo')->assertInertia(fn (Assert $a) => $a->has('results',1)->where('results.0.product.url',route('landing-presentations.show', $l)));
        $this->assertSame(route('landing-presentations.show',$l),app(ProductLandingResolver::class)->navigationUrl($p,false));
        $this->get('/productos-digitales/gluteo-perfecto/comprar')->assertOk()->assertInertia(fn ($page) => $page->component('digital/purchase')->where('authenticated', false));
        $this->assertSame(url('/productos-digitales/gluteo-perfecto/comprar'),session('url.intended'));
        $this->actingAs(User::factory()->create())->post('/productos-digitales/gluteo-perfecto/comprar')->assertSessionHasErrors('product');
        $this->assertDatabaseCount('digital_orders',0);
        $p->update(['status'=>'draft']);
        $this->get('/l/gluteo-perfecto')->assertNotFound();
    }
}
