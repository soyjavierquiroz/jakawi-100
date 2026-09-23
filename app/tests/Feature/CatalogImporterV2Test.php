<?php

namespace Tests\Feature;

use App\Console\Commands\CatalogV2TemplateCommand;
use App\Models\Experience;
use App\Models\ExperienceSession;
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
