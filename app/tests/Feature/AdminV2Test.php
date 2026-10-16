<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\City;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminV2Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function image(string $name): UploadedFile
    {
        $image = imagecreatetruecolor(80, 40);
        ob_start();
        match (pathinfo($name, PATHINFO_EXTENSION)) {
            'jpg' => imagejpeg($image),
            'png' => imagepng($image),
            'webp' => imagewebp($image),
        };
        $content = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_admin_creates_partner_location_pin_and_images(): void
    {
        Storage::fake(config('media.disk'));
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/partners', ['name' => 'Casa', 'slug' => 'casa', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'published', 'logo' => $this->image('logo.jpg'), 'cover' => $this->image('cover.png')])->assertRedirect()->assertSessionHasNoErrors();
        $partner = Partner::firstOrFail();
        Storage::disk(config('media.disk'))->assertExists($partner->logo_path);
        $cityId = City::where('slug', 'cochabamba')->value('id');
        $this->actingAs($admin)->post('/admin/locations', ['partner_id' => $partner->id, 'city_id' => $cityId, 'name' => 'Centro', 'slug' => 'centro', 'location_type' => 'branch', 'status' => 'published', 'redemption_pin' => '123456', 'image' => $this->image('image.webp')])->assertRedirect()->assertSessionHasNoErrors();
        $location = Location::firstOrFail();
        $this->assertTrue($location->checkRedemptionPin('123456'));
        $hash = $location->redemption_pin_hash;
        $this->actingAs($admin)->put('/admin/locations/'.$location->id, ['partner_id' => $partner->id, 'city_id' => $cityId, 'name' => 'Centro', 'slug' => 'centro', 'location_type' => 'branch', 'status' => 'published', 'redemption_pin' => ''])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($hash, $location->fresh()->redemption_pin_hash);
    }

    public function test_admin_uploads_accept_allowed_images_reject_invalid_files_and_preserve_old_files(): void
    {
        Storage::fake(config('media.disk'));
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/partners', ['name' => 'Media', 'slug' => 'media-studio', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'published', 'logo' => $this->image('logo.jpg')])->assertRedirect()->assertSessionHasNoErrors();
        $partner = Partner::firstOrFail();
        $old = $partner->logo_path;
        $this->actingAs($admin)->put('/admin/partners/'.$partner->id, ['name' => 'Media', 'slug' => 'media-studio', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'published', 'logo' => $this->image('logo.png'), 'cover' => $this->image('cover.webp')])->assertRedirect()->assertSessionHasNoErrors();
        $partner->refresh();
        Storage::disk(config('media.disk'))->assertExists($old);
        $this->assertNotSame($old, $partner->logo_path);
        Storage::disk(config('media.disk'))->assertExists($partner->logo_path);
        Storage::disk(config('media.disk'))->assertExists($partner->cover_path);
        $this->actingAs($admin)->post('/admin/partners', ['name' => 'Too large', 'slug' => 'too-large', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'published', 'logo' => UploadedFile::fake()->image('large.jpg')->size(10241)])->assertSessionHasErrors('logo');
        $this->actingAs($admin)->post('/admin/partners', ['name' => 'Invalid', 'slug' => 'invalid', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'published', 'logo' => UploadedFile::fake()->createWithContent('invalid.txt', 'not an image')])->assertSessionHasErrors('logo');
    }

    public function test_admin_enforces_benefit_location_ownership_and_experience_relations(): void
    {
        $admin = $this->admin();
        $partner = Partner::factory()->create();
        $other = Partner::factory()->create();
        $location = Location::factory()->for($other)->create();
        $response = $this->actingAs($admin)->post('/admin/benefits', ['partner_id' => $partner->id, 'title' => 'Oferta', 'slug' => 'oferta', 'status' => 'published', 'location_scope' => 'selected', 'location_ids' => [$location->id]]);
        $response->assertSessionHasErrors();
        $location->update(['partner_id' => $partner->id]);
        $this->actingAs($admin)->post('/admin/benefits', ['partner_id' => $partner->id, 'title' => 'Oferta', 'slug' => 'oferta', 'status' => 'published', 'location_scope' => 'selected', 'location_ids' => [$location->id]])->assertRedirect();
        $this->assertDatabaseHas('benefit_location', ['benefit_id' => Benefit::first()->id, 'location_id' => $location->id]);
        $this->actingAs($admin)->post('/admin/experiences', ['title' => 'Taller', 'slug' => 'taller', 'status' => 'published', 'reservation_method' => 'none', 'partners' => [['partner_id' => $partner->id, 'role' => 'host', 'sort_order' => 2]]])->assertRedirect();
        $experience = Experience::first();
        $this->assertSame('host', $experience->partners()->first()->pivot->role);
        $this->actingAs($admin)->post('/admin/experiences/'.$experience->slug.'/sessions', ['starts_at' => now()->addDay(), 'status' => 'scheduled', 'location_id' => $location->id])->assertRedirect();
        $this->assertDatabaseHas('experience_sessions', ['experience_id' => $experience->id]);
    }

    public function test_admin_access_membership_and_redemption_index(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $admin = $this->admin();
        $user = User::factory()->create();
        app(MembershipService::class)->activate($user, $admin);
        $this->actingAs($admin)->get('/admin/memberships?q='.urlencode($user->email))->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.per_page', 25)->where('users.data.0.id', $user->id)->where('users.data.0.membership.is_active', true));
        $membership = $user->activeMembership()->firstOrFail();
        $this->actingAs($admin)->delete('/admin/memberships/'.$membership->id)->assertRedirect();
        $this->assertSame('cancelled', $membership->fresh()->status);
        $this->actingAs($admin)->get('/admin/redemptions')->assertOk();
    }

    public function test_admin_can_view_locations_index(): void
    {
        $this->actingAs($this->admin())->get('/admin/locations')->assertOk();
    }

    public function test_admin_location_edit_uses_ids_for_draft_and_published(): void
    {
        $this->actingAs($this->admin());

        foreach (['draft', 'published'] as $status) {
            $location = Location::factory()->create(['status' => $status]);

            $this->get('/admin/locations/'.$location->id.'/edit')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('admin/resources/form')
                    ->where('item.id', $location->id)
                    ->where('item.status', $status));
        }
    }

    public function test_admin_location_update_uses_ids_without_changing_status(): void
    {
        $this->actingAs($this->admin());
        $cityId = City::where('slug', 'cochabamba')->value('id');

        foreach (['draft', 'published'] as $status) {
            $location = Location::factory()->create(['status' => $status, 'city_id' => $cityId]);

            $this->put('/admin/locations/'.$location->id, [
                'name' => 'Dirección administrativa actualizada',
                'slug' => $location->slug,
                'location_type' => $location->location_type,
                'status' => $status,
                'city_id' => $cityId,
            ])->assertRedirect(route('admin.locations.index'))->assertSessionHasNoErrors();

            $this->assertDatabaseHas('locations', [
                'id' => $location->id,
                'slug' => $location->slug,
                'name' => 'Dirección administrativa actualizada',
                'status' => $status,
                'city_id' => $cityId,
            ]);
        }
    }

    public function test_admin_location_unknown_ids_return_not_found(): void
    {
        $this->actingAs($this->admin());
        $missingId = (Location::max('id') ?? 0) + 1;

        $this->get('/admin/locations/'.$missingId.'/edit')->assertNotFound();
        $this->put('/admin/locations/'.$missingId, [])->assertNotFound();
    }

    public function test_admin_location_edit_and_update_require_admin(): void
    {
        $location = Location::factory()->create();
        $original = $location->fresh()->getAttributes();

        $this->get('/admin/locations/'.$location->id.'/edit')->assertRedirect('/login');
        $this->put('/admin/locations/'.$location->id, [])->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get('/admin/locations/'.$location->id.'/edit')->assertForbidden();
        $this->put('/admin/locations/'.$location->id, [])->assertForbidden();
        $this->assertSame($original, $location->fresh()->getAttributes());
    }

    public function test_public_location_binding_remains_slug(): void
    {
        $published = Location::factory()->published()->create(['maps_url' => 'https://maps.example.test/place']);
        $draft = Location::factory()->create(['status' => 'draft']);

        $this->assertSame('slug', $published->getRouteKeyName());
        $this->get('/lugares/'.$published->slug)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('locations/show')
                ->where('location.id', $published->id));
        $this->get('/lugares/'.$published->slug.'/mapa')->assertRedirect('https://maps.example.test/place');
        $this->get('/lugares/'.$published->id)->assertNotFound();
        $this->get('/lugares/'.$draft->slug)->assertNotFound();
    }
}
