<?php

namespace App\Console\Commands;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ImportCatalogV2Command extends Command
{
    protected $signature = 'jakawi:import-catalog-v2 {directory} {--apply : Persist the validated package}';

    protected $description = 'Validate and import a JAKAWI Catalog V2 CSV package (dry-run by default)';

    /** @var array<string, list<array{row:int,data:array<string,?string>}>> */
    private array $rows = [];

    /** @var list<array{file:string,row:int,field:string,message:string}> */
    private array $errors = [];

    /** @var array<string, array{create:int,update:int}> */
    private array $plan = [];

    public function handle(): int
    {
        $directory = rtrim((string) $this->argument('directory'), '/');
        foreach (CatalogV2TemplateCommand::HEADERS as $file => $headers) {
            $this->read($directory.'/'.$file, $file, $headers);
        }
        if ($this->errors === []) {
            $this->validate();
        }
        if ($this->errors !== []) {
            return $this->failed();
        }
        $this->makePlan();
        if (! $this->option('apply')) {
            $this->report('DRY RUN');

            return self::SUCCESS;
        }
        DB::transaction(fn () => $this->apply());
        $this->report('IMPORT APPLIED');

        return self::SUCCESS;
    }

    /** @param list<string> $headers */
    private function read(string $path, string $file, array $headers): void
    {
        if (! is_file($path)) {
            $this->errorAt($file, 0, 'file', 'Required file is missing.');

            return;
        }
        $contents = file_get_contents($path);
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            $this->errorAt($file, 0, 'file', 'File must be valid UTF-8.');

            return;
        }
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
        $first = array_shift($lines) ?? '';
        $actual = str_getcsv($first);
        if (count($actual) !== count(array_unique($actual))) {
            $this->errorAt($file, 1, 'header', 'Duplicate header.');
        }
        if ($actual !== $headers) {
            $this->errorAt($file, 1, 'header', 'Header must exactly match the Catalog V2 template.');
        }
        if ($this->errors !== []) {
            return;
        }
        $this->rows[$file] = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            if (count($values) !== count($headers)) {
                $this->errorAt($file, $index + 2, 'row', 'Column count does not match header.');

                continue;
            }
            $data = array_combine($headers, $values);
            $this->rows[$file][] = ['row' => $index + 2, 'data' => array_map(fn ($v) => trim($v) === '' ? null : trim($v), $data)];
        }
    }

    private function validate(): void
    {
        $slugFiles = ['partners.csv' => 'slug', 'locations.csv' => 'slug', 'benefits.csv' => 'slug', 'experiences.csv' => 'slug'];
        foreach ($slugFiles as $file => $field) {
            $this->unique($file, $field);
        }
        $this->unique('experience_sessions.csv', 'reference_key', fn ($r) => ($r['experience_slug'] ?? '').'|'.($r['reference_key'] ?? ''));
        $this->unique('experience_partners.csv', 'role', fn ($r) => implode('|', [$r['experience_slug'] ?? '', $r['partner_slug'] ?? '', $r['role'] ?? '']));
        foreach ($this->rows['partners.csv'] as $r) {
            $this->record($r, 'partners.csv', ['slug', 'name', 'entity_type', 'partner_type', 'status'], ['entity_type' => 'partner_entity_types', 'partner_type' => 'partner_types', 'category' => 'categories', 'status' => 'publication_statuses'], ['featured']);
        }
        foreach ($this->rows['locations.csv'] as $r) {
            $this->record($r, 'locations.csv', ['slug', 'name', 'location_type', 'status'], ['location_type' => 'location_types', 'status' => 'publication_statuses'], ['is_primary']);
            $this->number($r, 'locations.csv', 'latitude', -90, 90);
            $this->number($r, 'locations.csv', 'longitude', -180, 180);
            $this->json($r, 'locations.csv', 'opening_hours');
        }
        foreach ($this->rows['benefits.csv'] as $r) {
            $this->record($r, 'benefits.csv', ['slug', 'partner_slug', 'title', 'status', 'applies_to_all_locations'], ['category' => 'categories', 'benefit_type' => 'benefit_types', 'status' => 'publication_statuses'], ['featured', 'applies_to_all_locations']);
            $this->decimal($r, 'benefits.csv', 'estimated_savings');
            $this->positive($r, 'benefits.csv', 'redemption_limit_per_member');
            $this->dates($r, 'benefits.csv', 'starts_at', 'ends_at');
        }
        foreach ($this->rows['experiences.csv'] as $r) {
            $this->record($r, 'experiences.csv', ['slug', 'title', 'currency', 'reservation_method', 'status'], ['category' => 'categories', 'experience_type' => 'experience_types', 'reservation_method' => 'reservation_methods', 'status' => 'publication_statuses'], ['featured']);
            $this->decimal($r, 'experiences.csv', 'regular_price');
            $this->decimal($r, 'experiences.csv', 'member_price');
            if (($r['data']['currency'] ?? '') !== null && ! preg_match('/^[A-Z]{3}$/', $r['data']['currency'])) {
                $this->errorAt('experiences.csv', $r['row'], 'currency', 'Must be a three-letter uppercase currency.');
            } $this->positive($r, 'experiences.csv', 'duration_minutes');
            $d = $r['data'];
            if (($d['reservation_method'] ?? null) === 'whatsapp' && empty($d['reservation_whatsapp'])) {
                $this->errorAt('experiences.csv', $r['row'], 'reservation_whatsapp', 'Required for whatsapp.');
            } if (in_array($d['reservation_method'] ?? null, ['url', 'external'], true) && empty($d['reservation_url'])) {
                $this->errorAt('experiences.csv', $r['row'], 'reservation_url', 'Required for this reservation method.');
            } if (($d['reservation_method'] ?? null) === 'phone' && empty($d['reservation_phone'])) {
                $this->errorAt('experiences.csv', $r['row'], 'reservation_phone', 'Required for phone.');
            }
        }
        foreach ($this->rows['experience_partners.csv'] as $r) {
            $this->record($r, 'experience_partners.csv', ['experience_slug', 'partner_slug', 'role'], ['role' => 'experience_partner_roles'], []);
        }
        foreach ($this->rows['experience_sessions.csv'] as $r) {
            $this->record($r, 'experience_sessions.csv', ['experience_slug', 'reference_key', 'starts_at', 'status'], ['status' => 'experience_session_statuses'], []);
            $this->positive($r, 'experience_sessions.csv', 'capacity');
            $this->dates($r, 'experience_sessions.csv', 'starts_at', 'ends_at', true);
        }
        foreach (['partners.csv', 'locations.csv', 'benefits.csv', 'experiences.csv'] as $file) {
            foreach ($this->rows[$file] as $r) {
                $this->date($r, $file, 'published_at');
            }
        }
        if ($this->errors === []) {
            $this->references();
        }
    }

    /** @param array{row:int,data:array<string,?string>} $r @param list<string> $required @param array<string,string> $controlled @param list<string> $booleans */
    private function record(array $r, string $file, array $required, array $controlled, array $booleans): void
    {
        foreach ($required as $field) {
            if (empty($r['data'][$field])) {
                $this->errorAt($file, $r['row'], $field, 'Required.');
            }
        } foreach ($controlled as $field => $config) {
            if (($v = $r['data'][$field]) !== null && ! in_array($v, config('jakawi.'.$config), true)) {
                $this->errorAt($file, $r['row'], $field, 'Value is not allowed.');
            }
        } foreach ($booleans as $field) {
            if (($v = $r['data'][$field]) !== null && ! in_array(strtolower($v), ['1', '0', 'true', 'false'], true)) {
                $this->errorAt($file, $r['row'], $field, 'Must be 1, 0, true, or false.');
            }
        } foreach (['slug', 'experience_slug', 'partner_slug', 'location_slug'] as $field) {
            if (isset($r['data'][$field]) && $r['data'][$field] !== null && (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $r['data'][$field]) || str_starts_with($r['data'][$field], 'demo-'))) {
                $this->errorAt($file, $r['row'], $field, 'Must be a non-demo lowercase slug.');
            }
        }
    }

    /** @param array{row:int,data:array<string,?string>} $r */
    private function number(array $r, string $f, string $field, int $min, int $max): void
    {
        $v = $r['data'][$field];
        if ($v !== null && (! is_numeric($v) || (float) $v < $min || (float) $v > $max)) {
            $this->errorAt($f, $r['row'], $field, "Must be between {$min} and {$max}.");
        }
    }

    /** @param array{row:int,data:array<string,?string>} $r */
    private function decimal(array $r, string $f, string $field): void
    {
        $v = $r['data'][$field];
        if ($v !== null && (! preg_match('/^\d+(?:\.\d{1,2})?$/', $v))) {
            $this->errorAt($f, $r['row'], $field, 'Must be a non-negative decimal with up to two places.');
        }
    }

    /** @param array{row:int,data:array<string,?string>} $r */
    private function positive(array $r, string $f, string $field): void
    {
        $v = $r['data'][$field];
        if ($v !== null && (! ctype_digit($v) || (int) $v < 1)) {
            $this->errorAt($f, $r['row'], $field, 'Must be a positive integer.');
        }
    }

    /** @param array{row:int,data:array<string,?string>} $r */
    private function json(array $r, string $f, string $field): void
    {
        $v = $r['data'][$field];
        if ($v !== null && json_decode($v, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->errorAt($f, $r['row'], $field, 'Must be valid JSON.');
        }
    }

    /** @param array{row:int,data:array<string,?string>} $r */
    private function dates(array $r, string $f, string $start, string $end, bool $startRequired = false): void
    {
        $a = $this->date($r, $f, $start, $startRequired);
        $b = $this->date($r, $f, $end);
        if ($a && $b && $b->lte($a)) {
            $this->errorAt($f, $r['row'], $end, "Must be after {$start}.");
        }
    }

    /** @param array{row:int,data:array<string,?string>} $r */
    private function date(array $r, string $f, string $field, bool $required = false): ?Carbon
    {
        $v = $r['data'][$field];
        if ($v === null) {
            if ($required) {
                $this->errorAt($f, $r['row'], $field, 'Required.');
            }

return null;
        } try {
            return Carbon::parse($v);
        } catch (\Throwable) {
            $this->errorAt($f, $r['row'], $field, 'Must be a valid datetime.');

            return null;
        }
    }

    /** @param callable(array<string,?string>):string|null $key */
    private function unique(string $file, string $field, ?callable $key = null): void
    {
        $seen = [];
        foreach ($this->rows[$file] ?? [] as $r) {
            $v = $key ? $key($r['data']) : $r['data'][$field];
            if ($v !== null && $v !== '' && isset($seen[$v])) {
                $this->errorAt($file, $r['row'], $field, 'Duplicate value in CSV.');
            } $seen[$v] = true;
        }
    }

    private function references(): void
    {
        $partners = array_fill_keys(array_map(fn ($r) => $r['data']['slug'], $this->rows['partners.csv']), true) + Partner::pluck('id', 'slug')->all();
        $locations = array_fill_keys(array_map(fn ($r) => $r['data']['slug'], $this->rows['locations.csv']), true) + Location::pluck('id', 'slug')->all();
        $experiences = array_fill_keys(array_map(fn ($r) => $r['data']['slug'], $this->rows['experiences.csv']), true) + Experience::pluck('id', 'slug')->all();
        $locationOwners = Location::pluck('partner_id', 'slug')->all();
        foreach ($this->rows['locations.csv'] as $r) {
            $locationOwners[$r['data']['slug']] = $r['data']['partner_slug'];
        }
        foreach ($this->rows['locations.csv'] as $r) {
            if (($v = $r['data']['partner_slug']) !== null && ! isset($partners[$v])) {
                $this->errorAt('locations.csv', $r['row'], 'partner_slug', "Partner \"{$v}\" not found.");
            }
        }
        foreach ($this->rows['benefits.csv'] as $r) {
            $d = $r['data'];
            if (! isset($partners[$d['partner_slug']])) {
                $this->errorAt('benefits.csv', $r['row'], 'partner_slug', "Partner \"{$d['partner_slug']}\" not found.");
            } $all = in_array(strtolower((string) $d['applies_to_all_locations']), ['1', 'true'], true);
            $slugs = $d['location_slugs'] === null ? [] : explode('|', $d['location_slugs']);
            if ($all && $slugs) {
                $this->errorAt('benefits.csv', $r['row'], 'location_slugs', 'Must be empty when applies_to_all_locations is true.');
            } foreach ($slugs as $slug) {
                if (! isset($locations[$slug])) {
                    $this->errorAt('benefits.csv', $r['row'], 'location_slugs', "Location \"{$slug}\" not found.");
                } elseif (($locationOwners[$slug] ?? null) !== $d['partner_slug']) {
                    $this->errorAt('benefits.csv', $r['row'], 'location_slugs', 'All locations must belong to the benefit partner.');
                }
            }
        }
        foreach ($this->rows['experience_partners.csv'] as $r) {
            $d = $r['data'];
            if (! isset($experiences[$d['experience_slug']])) {
                $this->errorAt('experience_partners.csv', $r['row'], 'experience_slug', "Experience \"{$d['experience_slug']}\" not found.");
            }if (! isset($partners[$d['partner_slug']])) {
                $this->errorAt('experience_partners.csv', $r['row'], 'partner_slug', "Partner \"{$d['partner_slug']}\" not found.");
            }
        }
        foreach ($this->rows['experience_sessions.csv'] as $r) {
            $d = $r['data'];
            if (! isset($experiences[$d['experience_slug']])) {
                $this->errorAt('experience_sessions.csv', $r['row'], 'experience_slug', "Experience \"{$d['experience_slug']}\" not found.");
            }if ($d['location_slug'] !== null && ! isset($locations[$d['location_slug']])) {
                $this->errorAt('experience_sessions.csv', $r['row'], 'location_slug', "Location \"{$d['location_slug']}\" not found.");
            }
        }
    }

    private function makePlan(): void
    {
        foreach (['partners.csv' => 'Partners', 'locations.csv' => 'Locations', 'benefits.csv' => 'Benefits', 'experiences.csv' => 'Experiences'] as $file => $label) {
            $model = 'App\\Models\\'.rtrim($label, 's');
            $slugs = array_column(array_column($this->rows[$file], 'data'), 'slug');
            $found = $model::whereIn('slug', $slugs)->count();
            $this->plan[$label] = ['create' => count($slugs) - $found, 'update' => $found];
        } $this->plan['Experience partners'] = ['create' => count($this->rows['experience_partners.csv']), 'update' => 0];
        $found = 0;
        foreach ($this->rows['experience_sessions.csv'] as $r) {
            $e = Experience::where('slug', $r['data']['experience_slug'])->value('id');
            if ($e && ExperienceSession::where('experience_id', $e)->where('reference_key', $r['data']['reference_key'])->exists()) {
                $found++;
            }
        }$this->plan['Sessions'] = ['create' => count($this->rows['experience_sessions.csv']) - $found, 'update' => $found];
    }

    private function apply(): void
    {
        $p = [];
        $l = [];
        $e = [];
        foreach ($this->rows['partners.csv'] as $r) {
            $p[$r['data']['slug']] = $this->upsert(Partner::class, $r['data']);
        } foreach ($this->rows['locations.csv'] as $r) {
            $d = $r['data'];
            $d['partner_id'] = $d['partner_slug'] === null ? null : ($p[$d['partner_slug']]->id ?? Partner::where('slug', $d['partner_slug'])->value('id'));
            unset($d['partner_slug']);
            if ($d['opening_hours'] !== null) {
                $d['opening_hours'] = json_decode($d['opening_hours'], true);
            }$l[$d['slug']] = $this->upsert(Location::class, $d);
        } foreach ($this->rows['benefits.csv'] as $r) {
            $d = $r['data'];
            $d['partner_id'] = $p[$d['partner_slug']]->id ?? Partner::where('slug', $d['partner_slug'])->value('id');
            $slugs = $d['location_slugs'] === null ? [] : explode('|', $d['location_slugs']);
            unset($d['partner_slug'],$d['location_slugs']);
            $b = $this->upsert(Benefit::class, $d);
            if ($b->applies_to_all_locations) {
                $b->applyToAllLocations();
            } else {
                $b->syncLocations(array_map(fn ($s) => $l[$s]->id ?? Location::where('slug', $s)->value('id'), $slugs));
            }
        } foreach ($this->rows['experiences.csv'] as $r) {
            $e[$r['data']['slug']] = $this->upsert(Experience::class, $r['data']);
        } $groups = [];
        foreach ($this->rows['experience_partners.csv'] as $r) {
            $groups[$r['data']['experience_slug']][] = $r['data'];
        }foreach ($groups as $slug => $rows) {
            $x = $e[$slug] ?? Experience::where('slug', $slug)->firstOrFail();
            $x->syncPartnersWithRoles(array_map(fn ($d) => ['partner_id' => $p[$d['partner_slug']]->id ?? Partner::where('slug', $d['partner_slug'])->value('id'), 'role' => $d['role'], 'sort_order' => (int) ($d['sort_order'] ?? 0)], $rows));
        }foreach ($this->rows['experience_sessions.csv'] as $r) {
            $d = $r['data'];
            $x = $e[$d['experience_slug']] ?? Experience::where('slug', $d['experience_slug'])->firstOrFail();
            $d['experience_id'] = $x->id;
            $d['location_id'] = $d['location_slug'] === null ? null : ($l[$d['location_slug']]->id ?? Location::where('slug', $d['location_slug'])->value('id'));
            unset($d['experience_slug'],$d['location_slug']);
            ExperienceSession::updateOrCreate(['experience_id' => $x->id, 'reference_key' => $d['reference_key']], $d);
        }
    }

    /** @param class-string<Model> $class @param array<string,mixed> $data */
    private function upsert(string $class, array $data): object
    {
        foreach (['featured', 'is_primary', 'applies_to_all_locations'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = in_array(strtolower((string) $data[$key]), ['1', 'true'], true);
            }
        }

return $class::updateOrCreate(['slug' => $data['slug']], $data);
    }

    private function errorAt(string $file, int $row, string $field, string $message): void
    {
        $this->errors[] = ['file' => $file, 'row' => $row, 'field' => $field, 'message' => $message];
    }

    private function failed(): int
    {
        foreach ($this->errors as $e) {
            $this->line("{$e['file']} row {$e['row']} [{$e['field']}]: {$e['message']}");
        }$this->error('IMPORT FAILED');
        $this->line(count($this->errors).' errors');
        $this->line('0 writes');

        return self::FAILURE;
    }

    private function report(string $title): void
    {
        $this->info($title);
        foreach ($this->plan as $name => $v) {
            $this->line("{$name}: created {$v['create']}, updated {$v['update']}");
        }$this->line('ERRORS: 0');
    }
}
