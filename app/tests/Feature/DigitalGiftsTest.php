<?php

namespace Tests\Feature;

use App\Models\{DigitalAccessGrant, DigitalGift, DigitalProduct, Membership, Partner, User};
use App\Services\{DigitalGifts, MembershipService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalGiftsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $member;
    private DigitalProduct $product;
    private string $pdf = "%PDF-1.4\n% QA GIFT ONLY\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        config(['jakawi.prelaunch_mode' => false, 'digital_gifts.enabled' => true]);
        Storage::fake('digital-private');
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->member = User::factory()->create();
        Storage::disk('digital-private')->put('products/gift-qa.pdf', $this->pdf);
        $this->product = DigitalProduct::create(['partner_id' => Partner::where('slug', 'jakawi-digital')->value('id'),
            'name' => 'Regalo QA', 'slug' => 'regalo-qa', 'status' => 'published', 'currency' => 'BOB',
            'welcome_gift_enabled' => true, 'promotional_gift_enabled' => true,
            'file_path' => 'products/gift-qa.pdf', 'file_sha256' => hash('sha256', $this->pdf), 'gift_pdf_sha256' => hash('sha256', $this->pdf)]);
    }

    private function activate(): Membership
    {
        return app(MembershipService::class)->activate($this->member, $this->admin);
    }

    private function issue(?User $recipient = null): array
    {
        return app(DigitalGifts::class)->issue($this->product, $this->admin, $recipient, (string) Str::uuid());
    }

    public function test_first_activation_is_unique_and_renewal_and_existing_members_are_not_retroactive(): void
    {
        $membership = $this->activate();
        app(DigitalGifts::class)->firstActivation($membership);
        $this->assertDatabaseCount('digital_gifts', 1);
        app(MembershipService::class)->cancel($membership);
        $this->activate();
        $this->assertDatabaseCount('digital_gifts', 1);
        $old = User::factory()->create();
        config(['digital_gifts.enabled' => false]);
        $m = app(MembershipService::class)->activate($old, $this->admin);
        app(MembershipService::class)->cancel($m);
        config(['digital_gifts.enabled' => true]);
        app(MembershipService::class)->activate($old, $this->admin);
        $this->assertDatabaseCount('digital_gifts', 1);
    }

    public function test_active_pioneer_chooses_once_with_existing_explorer_and_keeps_download_after_expiry(): void
    {
        $membership = $this->activate();
        $membership->update(['type' => 'pioneer', 'ends_at' => now()->subDay()]);
        config(['jakawi.membership.official_launch_date' => null]);
        $this->actingAs($this->member)->get('/mi-jakawi')->assertInertia(fn (Assert $p) => $p->where('hasWelcomeGift', true)->etc());
        $this->get('/explorar?type=digital&gifts=1&q=Regalo&sort=name')->assertInertia(fn (Assert $p) => $p->component('explore')->where('giftFilter', true)->where('pagination.total', 1)->etc());
        $this->get('/productos-digitales/regalo-qa')->assertInertia(fn (Assert $p) => $p->where('giftAction.kind', 'welcome')->etc());
        $this->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertRedirect('/mi-jakawi/productos-digitales')->assertSessionHas('success', '¡Tu regalo está listo!');
        $this->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertStatus(409);
        $this->assertDatabaseCount('digital_access_grants', 1);
        $this->assertDatabaseCount('digital_orders', 0);
        $this->assertDatabaseCount('digital_payments', 0);
        $access = DigitalAccessGrant::sole();
        $this->assertNull($access->digital_order_id);
        $membership->update(['status' => 'cancelled']);
        $this->get('/mi-jakawi/productos-digitales/accesos/'.$access->id.'/pdf')->assertOk()->assertContent($this->pdf);
        $this->get('/productos-digitales/regalo-qa')->assertInertia(fn (Assert $p) => $p->where('giftAction.kind', 'owned')->etc());
        $this->get('/explorar?type=digital&gifts=1')->assertInertia(fn (Assert $p) => $p->where('hasWelcomeGift', false)->where('giftFilter', false)->etc());
        $this->actingAs($this->admin)->get('/mi-jakawi/productos-digitales/accesos/'.$access->id.'/pdf')->assertForbidden();
    }

    public function test_no_right_and_inactive_members_cannot_claim_and_incomplete_products_are_rejected(): void
    {
        $this->actingAs($this->member)->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertStatus(409);
        $m = $this->activate();
        $m->update(['ends_at' => now()->subHour()]);
        $this->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertForbidden();
        $m->update(['ends_at' => now()->addDay()]);
        $this->product->update(['gift_pdf_sha256' => null]);
        $this->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertStatus(422);
        $this->product->update(['gift_pdf_sha256' => $this->product->file_sha256]);
        Storage::disk('digital-private')->delete($this->product->file_path);
        $this->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertStatus(422);
        $this->assertDatabaseCount('digital_access_grants', 0);
        $this->assertDatabaseHas('digital_gifts', ['status' => 'pending']);
    }

    public function test_admin_link_is_bound_to_existing_user_and_token_is_hashed_and_consumed_once(): void
    {
        $key = (string) Str::uuid();
        $this->actingAs($this->member)->post('/admin/productos-digitales/regalo-qa/regalar', ['issuance_key' => $key])->assertForbidden();
        $response = $this->actingAs($this->admin)->postJson('/admin/productos-digitales/regalo-qa/regalar', ['issuance_key' => $key, 'user_id' => $this->member->id])->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $invitation = $response->json();
        $this->assertFalse(session()->has('digital_gift_invitation'));
        $token = basename($invitation['url']);
        $this->assertSame(hash('sha256', $token), DigitalGift::sole()->getRawOriginal('token_hash'));
        $this->assertArrayNotHasKey('token_hash', DigitalGift::sole()->toArray());
        $this->post('/admin/productos-digitales/regalo-qa/regalar', ['issuance_key' => $key])->assertStatus(409);
        $this->get($invitation['url'])->assertForbidden();
        $this->actingAs($this->member)->get($invitation['url'])->assertRedirect('/productos-digitales/regalo-qa');
        $this->post('/productos-digitales/regalo-qa/regalo-promocional', ['confirm' => true])->assertRedirect('/mi-jakawi/productos-digitales');
        $this->get($invitation['url'])->assertStatus(410);
        $this->withSession(['digital_gift_hash' => hash('sha256', $token)])->post('/productos-digitales/regalo-qa/regalo-promocional', ['confirm' => true])->assertStatus(409);
        $this->assertDatabaseCount('digital_access_grants', 1);
        $this->assertFalse($this->member->hasActiveMembership());
    }

    public function test_guest_registers_through_existing_flow_and_returns_to_the_gift(): void
    {
        $invitation = $this->issue();
        $this->get($invitation['url'])->assertRedirect('/productos-digitales/regalo-qa');
        $this->get('/productos-digitales/regalo-qa')->assertInertia(fn (Assert $p) => $p->where('giftAction.kind', 'promotional')->where('giftAction.authenticated', false)->etc());
        $this->get('/regalos-digitales/continuar')->assertRedirect('/login');
        $this->post('/register', ['name' => 'Destinataria QA', 'email' => 'gift-new@example.test', 'whatsapp' => '71234567'])->assertRedirect('/regalos-digitales/continuar');
        $user = User::whereEmail('gift-new@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->get('/regalos-digitales/continuar')->assertRedirect('/productos-digitales/regalo-qa');
        $this->post('/productos-digitales/regalo-qa/regalo-promocional', ['confirm' => true])->assertRedirect('/mi-jakawi/productos-digitales');
        $this->assertDatabaseHas('digital_access_grants', ['user_id' => $user->id, 'source' => 'gift']);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('digital_orders', 0);
    }

    public function test_expired_invitation_cannot_be_claimed_or_used_for_another_product(): void
    {
        $invitation = $this->issue();
        $hash = hash('sha256', basename($invitation['url']));
        $this->travel(15)->days();
        $this->get($invitation['url'])->assertStatus(410);
        $this->actingAs($this->member)->withSession(['digital_gift_hash' => $hash])->post('/productos-digitales/regalo-qa/regalo-promocional', ['confirm' => true])->assertStatus(410);
        $this->assertDatabaseCount('digital_access_grants', 0);
    }

    public function test_disabled_switch_and_prelaunch_preserve_operational_blocks(): void
    {
        config(['digital_gifts.enabled' => false]);
        $this->activate();
        $this->assertDatabaseCount('digital_gifts', 0);
        $this->actingAs($this->member)->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertForbidden();
        config(['digital_gifts.enabled' => true, 'jakawi.prelaunch_mode' => true]);
        $this->actingAs($this->admin)->post('/admin/productos-digitales/regalo-qa/regalar', ['issuance_key' => (string) Str::uuid()])->assertForbidden();
        $this->postJson('/productos-digitales/regalo-qa/comprar')->assertStatus(423);
        $this->postJson('/admin/memberships')->assertStatus(423);
        $this->assertDatabaseCount('digital_gifts', 0);
        $this->assertDatabaseCount('digital_access_grants', 0);
    }

    public function test_gift_pdf_uses_real_private_minio_and_immutable_grant_snapshot(): void
    {
        if (getenv('MINIO_INTEGRATION') !== '1') $this->markTestSkipped('Explicit real MinIO QA environment required.');
        config(['filesystems.disks.digital-private' => config('filesystems.disks.digital-private')]);
        // Storage::fake replaced the driver; restore the production S3 disk configuration.
        $diskConfig = require config_path('filesystems.php');
        config(['filesystems.disks.digital-private' => $diskConfig['disks']['digital-private']]);
        config(['filesystems.disks.digital-private.root' => 'digital-private/qa-gifts-'.Str::uuid()]);
        Storage::forgetDisk('digital-private');
        $disk = Storage::disk('digital-private');
        try {
            $disk->put($this->product->file_path, $this->pdf, ['visibility' => 'private']);
            $this->activate();
            $this->actingAs($this->member)->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertRedirect();
            $access = DigitalAccessGrant::sole();
            $this->product->update(['file_path' => 'products/new-version.pdf', 'file_sha256' => hash('sha256', 'new-version')]);
            $this->get('/mi-jakawi/productos-digitales/accesos/'.$access->id.'/pdf')->assertOk()->assertContent($this->pdf);
            $this->actingAs($this->admin)->get('/mi-jakawi/productos-digitales/accesos/'.$access->id.'/pdf')->assertForbidden();
            $cfg = config('filesystems.disks.digital-private');
            $url = rtrim($cfg['endpoint'], '/').'/'.$cfg['bucket'].'/'.$cfg['root'].'/'.$access->gift_file_path;
            $this->assertContains(\Illuminate\Support\Facades\Http::timeout(15)->get($url)->status(), [403,404]);
        } finally {
            foreach ($disk->allFiles() as $key) $disk->delete($key);
        }
    }
    private function preparePrelaunchCourtesy(): void
    {
        config(['digital_gifts.enabled' => false]);
        $this->activate(); // Existing member, without an automatic gift.
        config(['jakawi.prelaunch_mode' => true, 'digital_gifts.welcome_prelaunch_enabled' => true]);
        $this->product->update(['gift_pdf_sha256' => null, 'promotional_gift_enabled' => false]);
        \App\Models\AppSetting::create(['key' => DigitalGifts::COURTESY_KEY, 'value' => [
            'enabled' => true, 'product_id' => $this->product->id, 'pdf_sha256' => $this->product->file_sha256,
        ]]);
    }

    public function test_prelaunch_backfill_only_active_paid_members_and_pioneers_is_idempotent(): void
    {
        $this->preparePrelaunchCourtesy();
        $free = User::factory()->create();
        $pioneer = User::factory()->create();
        $unpaid = User::factory()->create();
        $expired = User::factory()->create();
        foreach ([[$pioneer, 'pioneer', 'active', 0], [$unpaid, 'standard', 'active', 0], [$expired, 'standard', 'cancelled', 100]] as [$user, $type, $status, $paid]) {
            Membership::withoutEvents(fn () => Membership::create(['user_id' => $user->id, 'type' => $type, 'status' => $status,
                'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'amount_paid' => $paid]));
        }
        $this->artisan('digital-gifts:backfill-welcome --execute')->assertSuccessful();
        $this->artisan('digital-gifts:backfill-welcome --execute')->assertSuccessful();
        $this->assertDatabaseCount('digital_gifts', 2);
        foreach ([$this->member, $pioneer] as $user) {
            $this->assertDatabaseHas('digital_gifts', ['user_id' => $user->id, 'kind' => 'welcome', 'status' => 'pending', 'digital_product_id' => null]);
        }
        foreach ([$free, $unpaid, $expired] as $user) $this->assertDatabaseMissing('digital_gifts', ['user_id' => $user->id]);
        $this->assertDatabaseCount('digital_access_grants', 0);
    }

    public function test_prelaunch_courtesy_full_claim_and_download_without_sales_or_promotional_gifts(): void
    {
        $this->preparePrelaunchCourtesy();
        app(DigitalGifts::class)->backfillWelcome($this->member);
        $this->actingAs($this->member)->get('/mi-jakawi')->assertInertia(fn (Assert $p) => $p
            ->where('membershipStatus.is_active', true)->where('membership.is_active', true)->where('hasWelcomeGift', true)->etc());
        $this->get('/explorar?type=digital&gifts=1')->assertInertia(fn (Assert $p) => $p->where('giftFilter', true)->where('pagination.total', 1)->etc());
        $this->get('/productos-digitales/regalo-qa')->assertInertia(fn (Assert $p) => $p->where('giftAction.kind', 'welcome')->where('canCollect', false)->etc());
        $this->postJson('/productos-digitales/regalo-qa/comprar')->assertStatus(423);
        $this->post('/productos-digitales/regalo-qa/regalo-promocional', ['confirm' => true])->assertForbidden();
        $this->actingAs($this->admin)->post('/admin/productos-digitales/regalo-qa/regalar', ['issuance_key' => (string) Str::uuid()])->assertForbidden();
        $this->actingAs($this->member)->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertRedirect('/mi-jakawi/productos-digitales');
        $access = DigitalAccessGrant::sole();
        $this->get('/mi-jakawi/productos-digitales/accesos/'.$access->id.'/pdf')->assertOk()->assertContent($this->pdf);
        $this->get('/mi-jakawi/productos-digitales/accesos/'.$access->id.'/pdf')->assertOk();
        $this->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertStatus(409);
        $this->actingAs($this->admin)->get('/mi-jakawi/productos-digitales/accesos/'.$access->id.'/pdf')->assertForbidden();
        $this->artisan('digital-gifts:backfill-welcome --execute')->assertSuccessful();
        $this->assertDatabaseCount('digital_gifts', 1);
        $this->assertDatabaseCount('digital_access_grants', 1);
        $this->assertDatabaseCount('digital_orders', 0);
        $this->assertDatabaseCount('digital_payments', 0);
        $this->assertNull($this->product->fresh()->gift_pdf_sha256);
        $this->assertFalse(app(\App\Services\DigitalSaleAvailability::class)->settings($this->product)['enabled'] ?? false);
    }

    public function test_prelaunch_welcome_exception_fails_closed_without_right_approval_or_switch(): void
    {
        $this->preparePrelaunchCourtesy();
        $free = User::factory()->create();
        $this->actingAs($free)->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertStatus(409);
        app(DigitalGifts::class)->backfillWelcome($this->member);
        $this->product->update(['file_sha256' => hash('sha256', 'changed')]);
        $this->actingAs($this->member)->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertStatus(422);
        config(['digital_gifts.welcome_prelaunch_enabled' => false]);
        $this->post('/productos-digitales/regalo-qa/regalo-bienvenida', ['confirm' => true])->assertForbidden();
        $this->assertDatabaseCount('digital_access_grants', 0);
        $this->assertDatabaseHas('digital_gifts', ['user_id' => $this->member->id, 'status' => 'pending']);
    }

}
