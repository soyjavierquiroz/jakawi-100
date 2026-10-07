<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class PublicJourneyConfig
{
    private const BLOCKS = ['benefits', 'image_text', 'social_proof', 'faq', 'rich_text'];

    public function registerRoutes(): void
    {
        $paths = [];
        foreach (config('public_journeys.landings', []) as $key => $definition) {
            $this->validateLanding($key, $definition);
            $path = $definition['path'];
            if (isset($paths[$path]) || $this->existingGetRoute($path)) {
                throw new InvalidArgumentException("Public journey path collides with an existing route: {$path}");
            }
            $paths[$path] = true;
            Route::get($path, [\App\Http\Controllers\PublicJourneyLandingController::class, 'show'])
                ->defaults('landingKey', $key)->name('public-journeys.'.str_replace('_', '-', $key));
        }
    }

    public function landing(string $key): ?array
    {
        $definition = config("public_journeys.landings.{$key}");
        if (! is_array($definition)) return null;
        $this->validateLanding($key, $definition);
        return $definition['status'] === 'active' ? $definition : null;
    }

    public function cta(array $definition): array
    {
        $cta = $definition['primary_cta'];
        if ($cta['type'] === 'external') {
            if (! $this->redirectEntry($cta['slug'])) throw new InvalidArgumentException('Public journey CTA redirect is unavailable.');
            return ['type' => 'external', 'label' => $cta['label'], 'href' => '/go/'.$cta['slug']];
        }
        $path = $cta['path'];
        if (! is_string($path) || ! preg_match('#^/(?:[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*)?$#D', $path)) {
            throw new InvalidArgumentException('Public journey CTA path is unsafe.');
        }
        $route = $this->existingGetRoute($path);
        if (! $route || $route->getName() === 'external-redirect.show') {
            throw new InvalidArgumentException("Public journey CTA must target a known internal GET route: {$path}");
        }
        return ['type' => 'internal', 'label' => $cta['label'], 'href' => $path,
            'destination_kind' => $route->getName() === 'experiences.show' ? 'experience' : null];
    }

    public function redirectEntry(string $slug): ?array
    {
        $entry = config("public_journeys.redirects.{$slug}");
        if (! is_array($entry) || ($entry['slug'] ?? null) !== $slug || ($entry['status'] ?? null) !== 'active') return null;
        if (isset($entry['expires_at']) && (! is_string($entry['expires_at']) || ! strtotime($entry['expires_at']) || now()->greaterThanOrEqualTo($entry['expires_at']))) return null;
        return $entry;
    }

    public function destination(array $entry): ?string
    {
        $value = $entry['destination'] ?? null;
        if (! is_string($value)) return null;
        return match ($entry['destination_type'] ?? null) {
            'phone' => preg_match('/^\+?[1-9]\d{6,14}$/D', $value) ? 'tel:'.$value : null,
            'whatsapp' => preg_match('/^\+?[1-9]\d{6,14}$/D', $value) ? 'https://wa.me/'.ltrim($value, '+') : null,
            'url' => $this->safeHttpsUrl($value) ? $value : null,
            default => null,
        };
    }

    private function safeHttpsUrl(string $value): bool
    {
        $parts = parse_url($value);
        return is_array($parts) && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host']) && filter_var($value, FILTER_VALIDATE_URL) !== false
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! str_contains($value, '\\') && ! preg_match('/[\x00-\x20\x7f]/', $value);
    }

    private function validateLanding(string $key, mixed $definition): void
    {
        if (! is_array($definition) || ($definition['key'] ?? null) !== $key
            || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key)
            || ! is_string($definition['path'] ?? null)
            || ! preg_match('#^/[a-z0-9]+(?:[a-z0-9/-]*[a-z0-9])?$#D', $definition['path'])
            || ! in_array($definition['status'] ?? null, ['active', 'inactive'], true)
            || ($definition['template'] ?? null) !== 'marketing'
            || ! is_string($definition['title'] ?? null) || ! is_string($definition['description'] ?? null)
            || ! is_string($definition['campaign_key'] ?? null)
            || strlen($definition['campaign_key']) > 100
            || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $definition['campaign_key'])
            || ! is_array($definition['blocks'] ?? null)
            || ! is_array($definition['primary_cta'] ?? null)
            || ! is_array($definition['seo'] ?? null)) {
            throw new InvalidArgumentException("Invalid public journey definition: {$key}");
        }
        foreach ($definition['blocks'] as $block) {
            if (! is_array($block) || ! in_array($block['type'] ?? null, self::BLOCKS, true)) {
                throw new InvalidArgumentException("Invalid public journey block: {$key}");
            }
        }
        $cta = $definition['primary_cta'];
        if (! is_string($cta['label'] ?? null)
            || ! in_array($cta['type'] ?? null, ['internal', 'external'], true)
            || ($cta['type'] === 'internal' && (! is_string($cta['path'] ?? null) || ! preg_match('#^/(?:[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*)?$#D', $cta['path'])))
            || ($cta['type'] === 'external' && (! is_string($cta['slug'] ?? null) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $cta['slug'])))) {
            throw new InvalidArgumentException("Invalid public journey CTA: {$key}");
        }
    }

    private function existingGetRoute(string $path): ?\Illuminate\Routing\Route
    {
        $request = Request::create($path, 'GET');
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true) && $route->matches($request, true)) return $route;
        }
        return null;
    }
}
