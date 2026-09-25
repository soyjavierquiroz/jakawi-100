<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $images = ['jpg' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AR//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AR//2Q==', 'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbhwAAAABJRU5ErkJggg==', 'webp' => 'UklGRiIAAABXRUJQVlA4IC4AAAAwAQCdASoBAAEAAUAmJaQAA3AA/vuUAAA='];
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return UploadedFile::fake()->createWithContent($name, base64_decode($images[$extension]));
    }

    public function test_admin_creates_partner_location_pin_and_images(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/partners', ['name' => 'Casa', 'slug' => 'casa', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'published', 'logo' => $this->image('logo.jpg'), 'cover' => $this->image('cover.png')])->assertRedirect();
        $partner = Partner::firstOrFail();
        Storage::disk('public')->assertExists($partner->logo_path);
        $this->actingAs($admin)->post('/admin/locations', ['partner_id' => $partner->id, 'name' => 'Centro', 'slug' => 'centro', 'location_type' => 'branch', 'status' => 'published', 'redemption_pin' => '123456', 'image' => $this->image('image.webp')])->assertRedirect();
        $location = Location::firstOrFail();
        $this->assertTrue($location->checkRedemptionPin('123456'));
        $hash = $location->redemption_pin_hash;
        $this->actingAs($admin)->put('/admin/locations/'.$location->slug, ['partner_id' => $partner->id, 'name' => 'Centro', 'slug' => 'centro', 'location_type' => 'branch', 'status' => 'published', 'redemption_pin' => ''])->assertRedirect();
        $this->assertSame($hash, $location->fresh()->redemption_pin_hash);
    }

    public function test_admin_uploads_accept_allowed_images_reject_invalid_files_and_replace_old_files(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/partners', ['name' => 'Media', 'slug' => 'media', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'published', 'logo' => $this->image('logo.jpg')])->assertRedirect();
        $partner = Partner::firstOrFail();
        $old = $partner->logo_path;
        $this->actingAs($admin)->put('/admin/partners/'.$partner->slug, ['name' => 'Media', 'slug' => 'media', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'published', 'logo' => $this->image('logo.png'), 'cover' => $this->image('cover.webp')])->assertRedirect();
        $partner->refresh();
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($partner->logo_path);
        Storage::disk('public')->assertExists($partner->cover_path);
        $this->actingAs($admin)->post('/admin/partners', ['name' => 'Too large', 'slug' => 'too-large', 'entity_type' => 'organization', 'partner_type' => 'business', 'status' => 'published', 'logo' => UploadedFile::fake()->createWithContent('large.jpg', base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AR//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AR//2Q==').str_repeat('x', 11 * 1024 * 1024))])->assertSessionHasErrors('logo');
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
        $this->actingAs($admin)->get('/admin/memberships')->assertOk();
        $membership = $user->activeMembership()->firstOrFail();
        $this->actingAs($admin)->delete('/admin/memberships/'.$membership->id)->assertRedirect();
        $this->assertSame('cancelled', $membership->fresh()->status);
        $this->actingAs($admin)->get('/admin/redemptions')->assertOk();
    }

    public function test_admin_can_view_locations_index(): void
    {
        $this->actingAs($this->admin())->get('/admin/locations')->assertOk();
    }
}
