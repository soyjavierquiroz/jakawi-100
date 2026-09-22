<?php

namespace App\Services;

use App\Models\Benefit;
use App\Models\Merchant;
use Illuminate\Support\Facades\Storage;

class DemoCatalogImageGenerator
{
    /** @return array{merchant_logos: int, merchant_covers: int, benefit_images: int, skipped: int} */
    public function generate(bool $force = false): array
    {
        $counts = ['merchant_logos' => 0, 'merchant_covers' => 0, 'benefit_images' => 0, 'skipped' => 0];

        Merchant::query()->where('slug', 'like', 'demo-%')->orderBy('id')->each(function (Merchant $merchant) use ($force, &$counts): void {
            $logoPath = "demo/merchants/{$merchant->slug}-logo.svg";
            $coverPath = "demo/merchants/{$merchant->slug}-cover.svg";

            $counts['merchant_logos'] += $this->write($logoPath, $this->merchantLogo($merchant), $force, $counts);
            $counts['merchant_covers'] += $this->write($coverPath, $this->merchantCover($merchant), $force, $counts);

            $merchant->forceFill(['logo_path' => $logoPath, 'cover_path' => $coverPath])->save();
        });

        Benefit::query()->with('merchant')->where('slug', 'like', 'demo-%')->orderBy('id')->each(function (Benefit $benefit) use ($force, &$counts): void {
            $path = "demo/benefits/{$benefit->slug}.svg";
            $counts['benefit_images'] += $this->write($path, $this->benefitImage($benefit), $force, $counts);

            $benefit->forceFill(['image_path' => $path])->save();
        });

        return $counts;
    }

    /** @param array{merchant_logos: int, merchant_covers: int, benefit_images: int, skipped: int} $counts */
    private function write(string $path, string $contents, bool $force, array &$counts): int
    {
        $disk = Storage::disk(config('jakawi.demo_catalog.images.disk'));

        if (! $force && $disk->exists($path) && str_contains((string) $disk->get($path), '<svg')) {
            $counts['skipped']++;

            return 0;
        }

        $disk->put($path, $contents, ['visibility' => 'public']);

        return 1;
    }

    private function merchantLogo(Merchant $merchant): string
    {
        [$width, $height] = config('jakawi.demo_catalog.images.dimensions.merchant_logo');
        $palette = $this->palette($merchant->slug);
        $initials = $this->initials($merchant->name);
        $offset = 80 + ($this->seed($merchant->slug) % 100);

        return $this->svg($width, $height, "
            <rect width=\"100%\" height=\"100%\" rx=\"96\" fill=\"{$palette['background']}\"/>
            <circle cx=\"{$offset}\" cy=\"390\" r=\"170\" fill=\"{$palette['accent']}\" opacity=\".25\"/>
            <circle cx=\"390\" cy=\"130\" r=\"150\" fill=\"{$palette['primary']}\" opacity=\".18\"/>
            <rect x=\"104\" y=\"104\" width=\"304\" height=\"304\" rx=\"110\" fill=\"{$palette['primary']}\"/>
            <text x=\"256\" y=\"290\" text-anchor=\"middle\" font-size=\"132\" font-weight=\"700\" fill=\"white\">{$this->escape($initials)}</text>
        ");
    }

    private function merchantCover(Merchant $merchant): string
    {
        [$width, $height] = config('jakawi.demo_catalog.images.dimensions.merchant_cover');
        $palette = $this->palette($merchant->slug);
        $category = config("jakawi.demo_catalog.images.category_labels.{$merchant->category}", 'JAKAWI');
        $seed = $this->seed($merchant->slug);
        $circleX = 780 + ($seed % 220);
        $circleY = 130 + (($seed >> 8) % 180);

        return $this->svg($width, $height, "
            <rect width=\"100%\" height=\"100%\" fill=\"{$palette['background']}\"/>
            <circle cx=\"{$circleX}\" cy=\"{$circleY}\" r=\"280\" fill=\"{$palette['primary']}\" opacity=\".85\"/>
            <circle cx=\"960\" cy=\"560\" r=\"250\" fill=\"{$palette['accent']}\" opacity=\".55\"/>
            <path d=\"M0 510 C250 390 410 690 710 535 S1040 350 1200 500 V630 H0Z\" fill=\"white\" opacity=\".5\"/>
            <text x=\"86\" y=\"130\" font-size=\"28\" font-weight=\"700\" letter-spacing=\"4\" fill=\"{$palette['text']}\">{$this->escape(strtoupper($category))}</text>
            <text x=\"80\" y=\"370\" font-size=\"76\" font-weight=\"700\" fill=\"{$palette['text']}\">{$this->escape($merchant->name)}</text>
            <text x=\"86\" y=\"430\" font-size=\"26\" fill=\"{$palette['text']}\" opacity=\".8\">Beneficios que te acompañan</text>
        ");
    }

    private function benefitImage(Benefit $benefit): string
    {
        [$width, $height] = config('jakawi.demo_catalog.images.dimensions.benefit');
        $palette = $this->palette($benefit->slug);
        $merchant = $benefit->merchant?->name ?? 'JAKAWI';
        $label = $benefit->benefit_type === '2x1' ? '2x1' : "Bs {$benefit->estimated_savings} ahorro";
        $seed = $this->seed($benefit->slug);
        $shape = 620 + ($seed % 280);

        return $this->svg($width, $height, "
            <rect width=\"100%\" height=\"100%\" fill=\"{$palette['background']}\"/>
            <circle cx=\"{$shape}\" cy=\"225\" r=\"260\" fill=\"{$palette['primary']}\" opacity=\".9\"/>
            <circle cx=\"1060\" cy=\"730\" r=\"260\" fill=\"{$palette['accent']}\" opacity=\".65\"/>
            <rect x=\"72\" y=\"76\" width=\"260\" height=\"76\" rx=\"38\" fill=\"{$palette['text']}\"/>
            <text x=\"202\" y=\"126\" text-anchor=\"middle\" font-size=\"29\" font-weight=\"700\" fill=\"white\">{$this->escape($label)}</text>
            <text x=\"76\" y=\"570\" font-size=\"30\" font-weight=\"700\" fill=\"{$palette['text']}\" opacity=\".78\">{$this->escape($merchant)}</text>
            <text x=\"72\" y=\"660\" font-size=\"60\" font-weight=\"700\" fill=\"{$palette['text']}\">{$this->escape($benefit->title)}</text>
        ");
    }

    /** @return array{background: string, primary: string, accent: string, text: string} */
    private function palette(string $slug): array
    {
        $palettes = config('jakawi.demo_catalog.images.palettes');

        return $palettes[$this->seed($slug) % count($palettes)];
    }

    private function seed(string $slug): int
    {
        return (int) sprintf('%u', crc32($slug));
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    }

    private function svg(int $width, int $height, string $content): string
    {
        $font = $this->escape(config('jakawi.demo_catalog.images.font_family'));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="Imagen demo de JAKAWI">
  <g font-family="{$font}">
    {$content}
  </g>
</svg>
SVG;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
