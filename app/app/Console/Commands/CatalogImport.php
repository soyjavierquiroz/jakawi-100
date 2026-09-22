<?php

namespace App\Console\Commands;

use App\Models\Benefit;
use App\Models\Merchant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CatalogImport extends Command
{
    public const HEADERS = [
        'merchant_slug', 'merchant_name', 'merchant_category', 'merchant_description',
        'merchant_city', 'merchant_address', 'merchant_instagram', 'merchant_whatsapp',
        'benefit_slug', 'benefit_title', 'benefit_description', 'benefit_terms',
        'benefit_type', 'estimated_savings', 'redemption_limit_per_member', 'featured',
        'starts_at', 'ends_at',
    ];

    protected $signature = 'jakawi:import-catalog {file : CSV file path} {--apply : Write validated changes to the database}';

    protected $description = 'Import real merchants and benefits from a CSV file (dry run by default).';

    /** @var list<array{row: int, field: string, error: string}> */
    private array $errors = [];

    public function handle(): int
    {
        $this->info('JAKAWI Catalog Import');
        $this->line('Mode: '.($this->option('apply') ? 'APPLY' : 'DRY RUN'));

        $rows = $this->readRows((string) $this->argument('file'));
        if ($rows === null) {
            return self::FAILURE;
        }

        $this->validateRows($rows);
        if ($this->errors !== []) {
            $this->displayErrors();

            return self::FAILURE;
        }

        $counts = $this->counts($rows);
        $this->displayCounts($counts, count($rows));

        if (! $this->option('apply')) {
            $this->info('No changes written.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($rows): void {
                /** @var array<string, Merchant> $merchants */
                $merchants = [];

                foreach ($rows as $row) {
                    $slug = $row['merchant_slug'];
                    $merchants[$slug] ??= Merchant::query()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => $row['merchant_name'],
                            'category' => $row['merchant_category'],
                            'description' => $row['merchant_description'],
                            'city' => $row['merchant_city'],
                            'address' => $row['merchant_address'],
                            'instagram' => $row['merchant_instagram'],
                            'whatsapp' => $row['merchant_whatsapp'],
                            'is_active' => true,
                        ],
                    );
                }

                foreach ($rows as $row) {
                    Benefit::query()->updateOrCreate(
                        ['slug' => $row['benefit_slug']],
                        [
                            'merchant_id' => $merchants[$row['merchant_slug']]->id,
                            'title' => $row['benefit_title'],
                            'description' => $row['benefit_description'],
                            'terms' => $row['benefit_terms'],
                            'benefit_type' => $row['benefit_type'],
                            'estimated_savings' => $row['estimated_savings'],
                            'redemption_limit_per_member' => $row['redemption_limit_per_member'],
                            'is_featured' => $row['featured'],
                            'starts_at' => $row['starts_at'],
                            'ends_at' => $row['ends_at'],
                            'is_active' => true,
                        ],
                    );
                }
            });
        } catch (\Throwable) {
            $this->error('Import failed. No changes were written.');

            return self::FAILURE;
        }

        $this->info('Import completed.');

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>>|null */
    private function readRows(string $file): ?array
    {
        $path = str_starts_with($file, '/') ? $file : base_path($file);
        if (! is_readable($path) || ($handle = fopen($path, 'r')) === false) {
            $this->error("CSV file cannot be read: {$file}");

            return null;
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            $this->error('CSV file is empty.');

            return null;
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? (string) $headers[0];
        $missing = array_diff(self::HEADERS, $headers);
        if ($missing !== []) {
            fclose($handle);
            $this->error('Missing required headers: '.implode(', ', $missing));

            return null;
        }

        $rows = [];
        $number = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $number++;
            if ($values === [null] || $values === []) {
                continue;
            }
            $record = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
            if ($record === false) {
                $this->errors[] = ['row' => $number, 'field' => 'CSV', 'error' => 'could not be parsed'];

                continue;
            }
            $record['_row'] = $number;
            $rows[] = $this->normalize($record);
        }
        fclose($handle);

        return $rows;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalize(array $row): array
    {
        foreach (self::HEADERS as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            $row[$field] = $value === '' ? null : preg_replace('/\s+/', ' ', $value);
        }
        foreach (['merchant_category', 'benefit_type'] as $field) {
            if ($row[$field] !== null) {
                $row[$field] = strtolower((string) $row[$field]);
            }
        }
        if ($row['featured'] !== null) {
            $row['featured'] = filter_var($row['featured'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        } else {
            $row['featured'] = false;
        }

        return $row;
    }

    /** @param list<array<string, mixed>> $rows */
    private function validateRows(array $rows): void
    {
        $merchantDefinitions = [];
        $benefitMerchants = [];
        foreach ($rows as $row) {
            $number = (int) $row['_row'];
            foreach (['merchant_slug', 'benefit_slug'] as $field) {
                if ($row[$field] === null) {
                    $this->addError($number, $field, 'is required');
                } elseif (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $row[$field])) {
                    $this->addError($number, $field, 'must be a valid lowercase slug');
                } elseif (str_starts_with((string) $row[$field], 'demo-')) {
                    $this->addError($number, $field, 'demo-* slugs are not allowed for real imports');
                }
            }
            foreach (['merchant_name', 'benefit_title'] as $field) {
                if ($row[$field] === null) {
                    $this->addError($number, $field, 'is required');
                } else {
                    $this->validateText($number, $field, (string) $row[$field], 255);
                }
            }
            foreach (['merchant_category', 'merchant_city', 'merchant_address', 'merchant_instagram', 'merchant_whatsapp', 'benefit_type'] as $field) {
                if ($row[$field] !== null) {
                    $this->validateText($number, $field, (string) $row[$field], 255);
                }
            }
            foreach (['merchant_description', 'benefit_description', 'benefit_terms'] as $field) {
                if ($row[$field] !== null) {
                    $this->validateText($number, $field, (string) $row[$field], 65535);
                }
            }
            if ($row['estimated_savings'] !== null && ! preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $row['estimated_savings'])) {
                $this->addError($number, 'estimated_savings', 'must be a decimal greater than or equal to 0');
            } elseif ($row['estimated_savings'] !== null && (float) $row['estimated_savings'] > 99999999.99) {
                $this->addError($number, 'estimated_savings', 'is too large');
            }
            if ($row['redemption_limit_per_member'] !== null && (! ctype_digit((string) $row['redemption_limit_per_member']) || (int) $row['redemption_limit_per_member'] < 1)) {
                $this->addError($number, 'redemption_limit_per_member', 'must be an integer greater than or equal to 1');
            }
            if (! is_bool($row['featured'])) {
                $this->addError($number, 'featured', 'must be a boolean value');
            }
            foreach (['starts_at', 'ends_at'] as $field) {
                if ($row[$field] !== null && ! $this->validDate((string) $row[$field])) {
                    $this->addError($number, $field, 'must be a valid date');
                }
            }
            if ($row['starts_at'] !== null && $row['ends_at'] !== null && $this->validDate((string) $row['starts_at']) && $this->validDate((string) $row['ends_at']) && CarbonImmutable::parse((string) $row['ends_at'])->lessThanOrEqualTo(CarbonImmutable::parse((string) $row['starts_at']))) {
                $this->addError($number, 'ends_at', 'must be after starts_at');
            }

            if ($row['merchant_slug'] !== null) {
                $definition = [$row['merchant_name'], $row['merchant_category']];
                if (isset($merchantDefinitions[$row['merchant_slug']]) && $merchantDefinitions[$row['merchant_slug']] !== $definition) {
                    $this->addError($number, 'merchant_slug', 'has contradictory merchant_name or merchant_category in this CSV');
                }
                $merchantDefinitions[$row['merchant_slug']] = $definition;
            }
            if ($row['benefit_slug'] !== null) {
                if (isset($benefitMerchants[$row['benefit_slug']]) && $benefitMerchants[$row['benefit_slug']] !== $row['merchant_slug']) {
                    $this->addError($number, 'benefit_slug', 'is associated with more than one merchant_slug in this CSV');
                }
                $benefitMerchants[$row['benefit_slug']] = $row['merchant_slug'];
            }
        }
    }

    private function validDate(string $value): bool
    {
        try {
            CarbonImmutable::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function validateText(int $row, string $field, string $value, int $max): void
    {
        if (mb_strlen($value) > $max) {
            $this->addError($row, $field, "may not be longer than {$max} characters");
        }
    }

    private function addError(int $row, string $field, string $error): void
    {
        $this->errors[] = compact('row', 'field', 'error');
    }

    /** @param list<array<string, mixed>> $rows @return array<string, int> */
    private function counts(array $rows): array
    {
        $merchantSlugs = array_values(array_unique(array_column($rows, 'merchant_slug')));
        $benefitSlugs = array_values(array_unique(array_column($rows, 'benefit_slug')));
        $existingMerchants = Merchant::query()->whereIn('slug', $merchantSlugs)->pluck('slug')->all();
        $existingBenefits = Benefit::query()->whereIn('slug', $benefitSlugs)->pluck('slug')->all();

        return [
            'merchant_create' => count(array_diff($merchantSlugs, $existingMerchants)),
            'merchant_update' => count($existingMerchants),
            'benefit_create' => count(array_diff($benefitSlugs, $existingBenefits)),
            'benefit_update' => count($existingBenefits),
        ];
    }

    /** @param array<string, int> $counts */
    private function displayCounts(array $counts, int $rows): void
    {
        $this->newLine();
        $this->line("Rows: {$rows}");
        $this->line('Merchants:');
        $this->line("Create: {$counts['merchant_create']}");
        $this->line("Update: {$counts['merchant_update']}");
        $this->line('Benefits:');
        $this->line("Create: {$counts['benefit_create']}");
        $this->line("Update: {$counts['benefit_update']}");
        $this->line('Errors: 0');
        $this->newLine();
    }

    private function displayErrors(): void
    {
        foreach ($this->errors as $error) {
            $this->error("Row {$error['row']} - {$error['field']}: {$error['error']}");
        }
        $this->line('Errors: '.count($this->errors));
        $this->line('No changes written.');
    }
}
