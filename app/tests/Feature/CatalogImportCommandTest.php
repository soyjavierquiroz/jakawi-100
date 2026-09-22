<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_write_and_apply_is_idempotent(): void
    {
        $file = $this->csv([$this->row(), $this->row(['benefit_slug' => 'fictional-cafe-pastry', 'benefit_title' => 'Pastry discount'])]);

        $this->artisan('jakawi:import-catalog', ['file' => $file])
            ->expectsOutput('Mode: DRY RUN')
            ->expectsOutput('No changes written.')
            ->assertSuccessful();
        $this->assertDatabaseCount('merchants', 0);
        $this->assertDatabaseCount('benefits', 0);

        $this->artisan('jakawi:import-catalog', ['file' => $file, '--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('merchants', 1);
        $this->assertDatabaseCount('benefits', 2);
        $this->assertDatabaseHas('benefits', ['slug' => 'fictional-cafe-2x1', 'merchant_id' => Merchant::firstOrFail()->id]);

        $this->artisan('jakawi:import-catalog', ['file' => $file, '--apply' => true])
            ->expectsOutput('Create: 0')
            ->assertSuccessful();
        $this->assertDatabaseCount('merchants', 1);
        $this->assertDatabaseCount('benefits', 2);
    }

    public function test_existing_records_update_without_overwriting_pin_or_images(): void
    {
        $merchant = Merchant::factory()->create([
            'slug' => 'fictional-cafe',
            'redemption_pin_hash' => Hash::make('123456'),
            'logo_path' => 'merchants/keep-logo.jpg',
            'cover_path' => 'merchants/keep-cover.jpg',
        ]);
        $benefit = Benefit::factory()->create([
            'merchant_id' => $merchant->id,
            'slug' => 'fictional-cafe-2x1',
            'image_path' => 'benefits/keep-image.jpg',
        ]);
        $file = $this->csv([$this->row(['merchant_name' => 'Updated Fictional Cafe', 'benefit_title' => 'Updated offer'])]);

        $this->artisan('jakawi:import-catalog', ['file' => $file, '--apply' => true])
            ->expectsOutput('Update: 1')
            ->assertSuccessful();

        $merchant->refresh();
        $benefit->refresh();
        $this->assertSame('Updated Fictional Cafe', $merchant->name);
        $this->assertTrue(Hash::check('123456', $merchant->redemption_pin_hash));
        $this->assertSame('merchants/keep-logo.jpg', $merchant->logo_path);
        $this->assertSame('merchants/keep-cover.jpg', $merchant->cover_path);
        $this->assertSame('Updated offer', $benefit->title);
        $this->assertSame('benefits/keep-image.jpg', $benefit->image_path);
    }

    #[DataProvider('invalidRows')]
    public function test_invalid_rows_are_rejected_without_writing(array $changes): void
    {
        $file = $this->csv([$this->row($changes)]);

        $this->artisan('jakawi:import-catalog', ['file' => $file, '--apply' => true])->assertFailed();
        $this->assertDatabaseCount('merchants', 0);
        $this->assertDatabaseCount('benefits', 0);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function invalidRows(): array
    {
        return [
            'demo slug' => [['merchant_slug' => 'demo-cafe']],
            'invalid decimal' => [['estimated_savings' => 'free']],
            'invalid date range' => [['starts_at' => '2026-12-31', 'ends_at' => '2026-10-01']],
        ];
    }

    public function test_conflicting_merchants_and_any_failure_prevent_partial_import(): void
    {
        $file = $this->csv([
            $this->row(),
            $this->row(['merchant_name' => 'Contradictory Cafe', 'benefit_slug' => 'fictional-cafe-second', 'benefit_title' => 'Second offer']),
        ]);

        $this->artisan('jakawi:import-catalog', ['file' => $file, '--apply' => true])->assertFailed();
        $this->assertDatabaseCount('merchants', 0);
        $this->assertDatabaseCount('benefits', 0);
    }

    /** @param array<string, string> $changes @return array<string, string> */
    private function row(array $changes = []): array
    {
        return array_replace(array_combine($this->headers(), [
            'fictional-cafe', 'Fictional Cafe', 'coffee', 'A fictional cafe.', 'Merida', '1 Example St', '@fictionalcafe', '+529990000000',
            'fictional-cafe-2x1', '2 for 1 coffee', 'Fictional offer.', 'One per member.', '2x1', '45.50', '1', 'true', '2026-10-01', '2026-12-31',
        ]), $changes);
    }

    /** @param list<array<string, string>> $rows */
    private function csv(array $rows): string
    {
        $path = storage_path('framework/testing/catalog-import-'.uniqid().'.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'w');
        fputcsv($handle, $this->headers());
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    /** @return list<string> */
    private function headers(): array
    {
        return [
            'merchant_slug', 'merchant_name', 'merchant_category', 'merchant_description', 'merchant_city', 'merchant_address', 'merchant_instagram', 'merchant_whatsapp',
            'benefit_slug', 'benefit_title', 'benefit_description', 'benefit_terms', 'benefit_type', 'estimated_savings', 'redemption_limit_per_member', 'featured', 'starts_at', 'ends_at',
        ];
    }
}
