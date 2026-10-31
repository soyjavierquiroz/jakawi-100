<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\MembershipBankCheckout;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembershipBankQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio-media');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function upload(string $type = 'png'): array
    {
        $file = UploadedFile::fake()->image('bank-test.'.$type, 700, 900);
        $bytes = file_get_contents($file->getRealPath());
        $this->post('/admin/conciliacion-membresias/qr', ['qr_image' => $file])->assertRedirect('/admin/conciliacion-membresias#configuracion-qr');

        return [$bytes, AppSetting::where('key', 'membership_bank_qr')->sole()->value];
    }

    public function test_admin_can_upload_and_checkout_serves_identical_original_png(): void
    {
        $this->actingAs($this->admin())->get('/admin/conciliacion-membresias')->assertOk()->assertSee('Configuración del QR bancario')->assertSee('cobros deshabilitados');
        [$bytes, $value] = $this->upload();
        $this->assertSame($bytes, Storage::disk('minio-media')->get($value['path']));
        $this->assertSame('private', Storage::disk('minio-media')->getVisibility($value['path']));
        $this->assertSame(hash('sha256', $bytes), $value['sha256']);
        $this->assertSame(700, $value['width']);
        $this->assertSame(900, $value['height']);
        $this->get('/admin/conciliacion-membresias/qr')->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent($bytes);
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->get('/membresia/checkout')->assertOk()->assertSee('Ya pagué')->assertSee('/membresia/checkout/qr', false);
        $this->get('/membresia/checkout/qr')->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent($bytes)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertTrue(app(MembershipBankCheckout::class)->ready());
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('membership_bank_imports', 0);
        $this->assertDatabaseCount('commercial_outbox', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership_bank_qr_saved']);
    }

    public function test_replacing_qr_with_jpg_keeps_original_bytes_and_applies_without_config_cache_changes(): void
    {
        $this->actingAs($this->admin());
        [$png] = $this->upload();
        [$jpg, $value] = $this->upload('jpg');
        $this->assertNotSame($png, $jpg);
        $this->assertSame('image/jpeg', $value['mime']);
        $this->assertSame($jpg, Storage::disk('minio-media')->get($value['path']));
        $this->get('/membresia/checkout/qr')->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertContent($jpg);
        $this->get('/admin/conciliacion-membresias')->assertOk()->assertSee('Reemplazar imagen PNG/JPG')->assertSee('Pago habilitado');
    }

    public function test_missing_or_corrupt_minio_object_disables_payment(): void
    {
        $this->actingAs($this->admin());
        [, $value] = $this->upload();
        Storage::disk('minio-media')->put($value['path'], str_repeat('x', $value['size']));
        $this->assertFalse(app(MembershipBankCheckout::class)->ready());
        $this->get('/membresia/checkout/qr')->assertNotFound();
        $this->post('/membresia/checkout/comprobante')->assertStatus(422);
        Storage::disk('minio-media')->delete($value['path']);
        $this->assertFalse(app(MembershipBankCheckout::class)->ready());
    }

    public function test_unconfigured_qr_and_legacy_env_path_do_not_enable_collection(): void
    {
        config(['membership_checkout.qr_path' => 'qr/bank.png']);
        $this->assertFalse(app(MembershipBankCheckout::class)->ready());
        $this->actingAs(User::factory()->create())->get('/membresia/checkout')->assertOk()->assertDontSee('Ya pagué')->assertSee('Aún no habilitamos los cobros');
        $this->get('/membresia/checkout/qr')->assertNotFound();
    }

    public function test_invalid_upload_preserves_current_qr(): void
    {
        $this->actingAs($this->admin());
        [$bytes, $value] = $this->upload();
        foreach ([UploadedFile::fake()->createWithContent('bad.png', 'not-an-image'), UploadedFile::fake()->image('tiny.jpg', 10, 10), UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')] as $file) {
            $this->post('/admin/conciliacion-membresias/qr', ['qr_image' => $file])->assertSessionHasErrors('qr_image');
            $this->assertSame($value, AppSetting::where('key', 'membership_bank_qr')->sole()->value);
        }
        $this->get('/membresia/checkout/qr')->assertContent($bytes);
    }

    public function test_storage_failure_preserves_previous_configuration(): void
    {
        $this->actingAs($this->admin());
        [, $value] = $this->upload();
        $failing = \Mockery::mock(FilesystemAdapter::class);
        $failing->shouldReceive('put')->once()->andThrow(new \RuntimeException('storage unavailable'));
        $failing->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('minio-media')->andReturn($failing);
        $this->post('/admin/conciliacion-membresias/qr', ['qr_image' => UploadedFile::fake()->image('qr.png', 400, 400)])->assertSessionHasErrors('qr_image');
        $this->assertSame($value, AppSetting::where('key', 'membership_bank_qr')->sole()->value);
    }

    public function test_upload_and_admin_preview_require_admin_and_checkout_image_requires_login(): void
    {
        $this->get('/admin/conciliacion-membresias/qr')->assertRedirect('/login');
        $this->post('/admin/conciliacion-membresias/qr')->assertRedirect('/login');
        $this->get('/membresia/checkout/qr')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->post('/admin/conciliacion-membresias/qr')->assertForbidden();
        $this->get('/admin/conciliacion-membresias/qr')->assertForbidden();
        $this->assertDatabaseMissing('app_settings', ['key' => 'membership_bank_qr']);
    }
}
