<?php

namespace Tests\Feature;

use App\Console\Commands\CatalogV2TemplateCommand;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\{Benefit, Location, Partner};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogImporterV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_template_creates_exact_empty_csvs(): void
    {
        $dir = $this->directory();
        $this->artisan('jakawi:catalog-v2-template', ['directory' => $dir])->assertSuccessful();
        foreach (CatalogV2TemplateCommand::HEADERS as $file => $headers) {
            $this->assertFileExists($dir.'/'.$file);
            $this->assertSame(implode(',', $headers)."\n", file_get_contents($dir.'/'.$file));
        }
        $this->assertFileExists($dir.'/README.txt');
    }

    public function test_dry_run_has_zero_writes_and_apply_is_idempotent(): void
    {
        $dir = $this->package();
        $this->artisan('jakawi:import-catalog-v2', ['directory' => $dir])->expectsOutputToContain('DRY RUN')->assertSuccessful();
        $this->assertDatabaseCount('partners', 0);
        $this->artisan('jakawi:import-catalog-v2', ['directory' => $dir, '--apply' => true])->expectsOutputToContain('IMPORT APPLIED')->assertSuccessful();
        $this->assertDatabaseCount('partners', 1);
        $this->assertDatabaseCount('locations', 1);
        $this->assertDatabaseCount('benefits', 1);
        $this->assertDatabaseCount('experiences', 1);
        $this->assertDatabaseCount('experience_sessions', 1);
        $this->artisan('jakawi:import-catalog-v2', ['directory' => $dir, '--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('experience_sessions', 1);
        $this->assertSame('session-a', ExperienceSession::firstOrFail()->reference_key);
    }

    public function test_invalid_late_row_has_no_partial_writes(): void
    {
        $dir = $this->package();
        file_put_contents($dir.'/experience_sessions.csv', implode(',', CatalogV2TemplateCommand::HEADERS['experience_sessions.csv'])."\nmissing,session-a,,2026-10-01 12:00:00,,10,scheduled,Room\n");
        $this->artisan('jakawi:import-catalog-v2', ['directory' => $dir, '--apply' => true])->expectsOutputToContain('IMPORT FAILED')->assertFailed();
        $this->assertDatabaseCount('partners', 0);
    }

    public function test_reference_key_is_nullable_but_unique_per_experience_when_present(): void
    {
        $experience = Experience::factory()->create();
        ExperienceSession::factory()->create(['experience_id' => $experience->id, 'reference_key' => null]);
        ExperienceSession::factory()->create(['experience_id' => $experience->id, 'reference_key' => null]);
        ExperienceSession::factory()->create(['experience_id' => $experience->id, 'reference_key' => 'same']);
        $this->expectException(UniqueConstraintViolationException::class);
        ExperienceSession::factory()->create(['experience_id' => $experience->id, 'reference_key' => 'same']);
    }

    public function test_reimport_updates_catalog_and_preserves_admin_only_fields(): void
    {
        $dir = $this->package();
        $this->artisan('jakawi:import-catalog-v2', ['directory' => $dir, '--apply' => true])->assertSuccessful();
        $partner = Partner::firstOrFail(); $location = Location::firstOrFail(); $benefit = Benefit::firstOrFail(); $experience = Experience::firstOrFail(); $session = ExperienceSession::firstOrFail();
        $ids = [$partner->id, $location->id, $benefit->id, $experience->id, $session->id];
        $partner->update(['logo_path' => 'logo.jpg', 'cover_path' => 'cover.jpg']);
        $location->forceFill(['redemption_pin_hash' => 'unchanged-hash', 'image_path' => 'location.jpg', 'manager_name' => 'Manager', 'manager_phone' => '+59170000000', 'manager_email' => 'manager@example.test'])->save();
        $benefit->update(['image_path' => 'benefit.jpg']); $experience->update(['image_path' => 'experience.jpg', 'cover_path' => 'experience-cover.jpg']);
        $this->writeCsv($dir, 'partners.csv', [['p-one', 'Partner', 'organization', 'business', '', '', 'Updated', 'food', '', '', '', '', '', '', '', '', '', '', 'published', 'true', '', '0', '']]);
        $this->writeCsv($dir, 'locations.csv', [['loc-one', 'p-one', 'Location', 'branch', 'published', 'true', 'BO', '', '', '', 'Updated address', '', '-17.3', '-66.1', '', '', '', '', '', '', '', '', '', 'America/La_Paz', '{"mon":[]}', '0', '']]);
        $this->writeCsv($dir, 'benefits.csv', [['b-one', 'p-one', 'Benefit', '', '', '', 'food', 'percentage', '20.00', '2', 'published', 'false', '', '', 'false', 'loc-one', '0', '']]);
        $this->writeCsv($dir, 'experiences.csv', [['e-one', 'Experience', '', '', '', 'experiences', 'workshop', '60', '20.00', '15.00', 'BOB', 'none', '', '', '', 'published', 'false', '0', '']]);
        $this->writeCsv($dir, 'experience_partners.csv', [['e-one', 'p-one', 'organizer', '3']]);
        $this->writeCsv($dir, 'experience_sessions.csv', [['e-one', 'session-a', '', '2026-10-02 12:00:00', '2026-10-02 13:00:00', '20', 'scheduled', 'New room']]);
        $this->artisan('jakawi:import-catalog-v2', ['directory' => $dir, '--apply' => true])->assertSuccessful();
        $this->assertSame($ids, [Partner::firstOrFail()->id, Location::firstOrFail()->id, Benefit::firstOrFail()->id, Experience::firstOrFail()->id, ExperienceSession::firstOrFail()->id]);
        $this->assertSame('Updated', Partner::firstOrFail()->description); $this->assertSame('Updated address', Location::firstOrFail()->address); $this->assertSame('20.00', Benefit::firstOrFail()->estimated_savings); $this->assertSame('15.00', Experience::firstOrFail()->member_price); $this->assertSame(20, ExperienceSession::firstOrFail()->capacity);
        $this->assertDatabaseHas('partners', ['id' => $ids[0], 'logo_path' => 'logo.jpg', 'cover_path' => 'cover.jpg']);
        $this->assertDatabaseHas('locations', ['id' => $ids[1], 'redemption_pin_hash' => 'unchanged-hash', 'image_path' => 'location.jpg', 'manager_name' => 'Manager']);
        $this->assertDatabaseHas('benefits', ['id' => $ids[2], 'image_path' => 'benefit.jpg']); $this->assertDatabaseHas('experiences', ['id' => $ids[3], 'image_path' => 'experience.jpg', 'cover_path' => 'experience-cover.jpg']);
    }

    public function test_invalid_input_reports_file_row_field_and_never_writes(): void
    {
        $dir = $this->package();
        $this->writeCsv($dir, 'partners.csv', [['demo-partner', 'Partner', 'invalid', 'bad', '', '', '', 'bad', '', '', '', '', '', '', '', '', '', '', 'bad', 'yes', '', '0', '']]);
        $this->artisan('jakawi:import-catalog-v2', ['directory' => $dir, '--apply' => true])->expectsOutputToContain('partners.csv row 2 [entity_type]')->expectsOutputToContain('IMPORT FAILED')->expectsOutputToContain('0 writes')->assertFailed();
        $this->assertDatabaseCount('partners', 0);
    }

    private function package(): string
    {
        $dir = $this->directory();
        $this->artisan('jakawi:catalog-v2-template', ['directory' => $dir])->assertSuccessful();
        $this->writeCsv($dir, 'partners.csv', [['p-one', 'Partner', 'organization', 'business', '', '', '', 'food', '', '', '', '', '', '', '', '', '', '', 'published', 'true', '', '0', '']]);
        $this->writeCsv($dir, 'locations.csv', [['loc-one', 'p-one', 'Location', 'branch', 'published', 'true', 'BO', '', '', '', 'Address', '', '-17.3', '-66.1', '', '', '', '', '', '', '', '', '', 'America/La_Paz', '{"mon":[]}', '0', '']]);
        $this->writeCsv($dir, 'benefits.csv', [['b-one', 'p-one', 'Benefit', '', '', '', 'food', 'percentage', '10.00', '', 'published', 'false', '', '', 'false', 'loc-one', '0', '']]);
        $this->writeCsv($dir, 'experiences.csv', [['e-one', 'Experience', '', '', '', 'experiences', 'workshop', '60', '20.00', '10.00', 'BOB', 'none', '', '', '', 'published', 'false', '0', '']]);
        $this->writeCsv($dir, 'experience_partners.csv', [['e-one', 'p-one', 'host', '0']]);
        $this->writeCsv($dir, 'experience_sessions.csv', [['e-one', 'session-a', 'loc-one', '2026-10-01 12:00:00', '2026-10-01 13:00:00', '10', 'scheduled', 'Room']]);

        return $dir;
    }

    /** @param list<list<string>> $rows */
    private function writeCsv(string $dir, string $file, array $rows): void
    {
        $handle = fopen($dir.'/'.$file, 'w');
        fputcsv($handle, CatalogV2TemplateCommand::HEADERS[$file]);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        } fclose($handle);
    }

    private function directory(): string
    {
        $dir = sys_get_temp_dir().'/jakawi-catalog-'.uniqid();
        mkdir($dir);

        return $dir;
    }
}
