<?php
namespace App\Services;

use App\Models\{DigitalAccessGrant, DigitalProduct};
use Illuminate\Support\Facades\{Cache, Http};

class HostedApps
{
    // Only operator-imported slugs; URLs are always derived from our STORE origin.
    public function registry(): array
    {
        $apps = config('hosted_apps.apps', []);
        $slugs = app(DigitalAdminSnapshot::class)->setting('hosted_app_catalog')['slugs'] ?? [];
        foreach ($slugs as $slug) {
            if (is_string($slug) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
                $apps[$slug] = ['entry' => "https://jakawi.store/$slug/_entry", 'health' => "https://jakawi.store/$slug/_health"];
            }
        }
        return $apps;
    }

    public function registered(DigitalProduct $product): bool
    {
        return $product->delivery_type === 'hosted_app' && filled(config('hosted_apps.secret'))
            && isset($this->registry()[$product->app_key]);
    }

    public function ready(DigitalProduct $product, bool $fresh = false): bool
    {
        if (! $this->registered($product)) return false;
        $snapshot = app(DigitalAdminSnapshot::class);
        if ($snapshot->active && ! $fresh) return ($snapshot->health['hosted-app-health:'.$product->app_key] ?? false) === true;
        if ($fresh) Cache::store('database')->forget('hosted-app-health:'.$product->app_key);
        return Cache::store('database')->remember('hosted-app-health:'.$product->app_key, 30, function () use ($product) {
            try {
                $r = Http::withToken(config('hosted_apps.secret'))->timeout(20)->get($this->registry()[$product->app_key]['health']);
                if (! $this->healthyResponse($r)) return false;
                // Probe without credentials or redirects: health alone cannot prove route protection.
                $base = 'https://jakawi.store/'.$product->app_key.'/';
                // Adopted Glúteos serves a generic public JS bundle; its rendered
                // screen and index remain guarded. Keep that existing delivery contract.
                $paths = $product->app_key === 'gluteos' ? ['', 'index.php'] : ['', 'index.php', 'app.js'];
                foreach ($paths as $path) {
                    $direct = Http::withoutRedirecting()->timeout(8)->get($base.$path);
                    if (! $this->guardedResponse($direct)) return false;
                }
                return true;
            } catch (\Throwable) { return false; }
        });
    }

    private function healthyResponse(mixed $response): bool
    {
        return $response instanceof \Illuminate\Http\Client\Response && $response->ok()
            && $response->json('protected') === true && $response->json('functional') === true;
    }

    private function guardedResponse(mixed $response): bool
    {
        return $response instanceof \Illuminate\Http\Client\Response && in_array($response->status(), [401,403], true);
    }

    /** Batch health reads for the admin; authorization still checks fresh before publishing. */
    public function adminHealth(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));
        $cache = Cache::store('database');
        $health = $cache->many(array_map(fn ($key) => 'hosted-app-health:'.$key, $keys));
        $registry = $this->registry();
        $missing = array_values(array_filter($keys, fn ($key) => !isset($health['hosted-app-health:'.$key]) && isset($registry[$key]) && filled(config('hosted_apps.secret'))));
        foreach (array_chunk($missing, 20) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $registry) {
                $requests = [];
                foreach ($chunk as $key) {
                    $requests[] = $pool->as($key.':health')->withToken(config('hosted_apps.secret'))->timeout(20)->get($registry[$key]['health']);
                    foreach ($key === 'gluteos' ? ['', 'index.php'] : ['', 'index.php', 'app.js'] as $path) {
                        $requests[] = $pool->as($key.':'.$path)->withoutRedirecting()->timeout(8)->get('https://jakawi.store/'.$key.'/'.$path);
                    }
                }
                return $requests;
            });
            $values = [];
            foreach ($chunk as $key) {
                $r = $responses[$key.':health'] ?? null;
                $valid = $this->healthyResponse($r);
                foreach ($key === 'gluteos' ? ['', 'index.php'] : ['', 'index.php', 'app.js'] as $path) {
                    $r = $responses[$key.':'.$path] ?? null;
                    $valid = $valid && $this->guardedResponse($r);
                }
                $values['hosted-app-health:'.$key] = $valid;
            }
            $cache->putMany($values,30);
            $health = array_replace($health,$values);
        }
        return $health;
    }

    public function authorized(DigitalAccessGrant $grant): bool
    {
        return $grant->status === 'active' && ! $grant->revoked_at && $grant->delivery_status === 'ready'
            && $this->registered(DigitalProduct::findOrFail($grant->digital_product_id))
            && ($grant->source === 'gift' || $grant->order?->status === 'confirmed');
    }

    public function issue(DigitalAccessGrant $grant): string
    {
        abort_unless($this->authorized($grant), 403);
        $token = bin2hex(random_bytes(32));
        Cache::store('database')->put('hosted-pass:'.hash('sha256', $token), ['grant' => $grant->id, 'user' => $grant->user_id, 'product' => $grant->digital_product_id], 60);
        return $token;
    }

    public function consume(string $token, string $app): array
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 403);
        $key = 'hosted-pass:'.hash('sha256', $token);
        $pass = Cache::store('database')->lock($key.':lock', 5)->block(3, fn () => Cache::store('database')->pull($key));
        abort_unless($pass, 403);
        $grant = DigitalAccessGrant::findOrFail($pass['grant']);
        abort_unless($grant->user_id === $pass['user'] && $grant->digital_product_id === $pass['product'] && $this->authorized($grant)
            && DigitalProduct::findOrFail($grant->digital_product_id)->app_key === $app, 403);
        // This encrypted credential never travels through the browser. STORE keeps it in its server session.
        return ['credential' => encrypt(['grant' => $grant->id, 'user' => $grant->user_id, 'product' => $grant->digital_product_id, 'app' => $app, 'until' => now()->addHours(8)->timestamp])];
    }

    public function check(string $credential, string $app): bool
    {
        try {
            $p = decrypt($credential);
            $grant = DigitalAccessGrant::findOrFail($p['grant']);
            return $p['until'] > time() && $p['app'] === $app && $grant->user_id === $p['user'] && $grant->digital_product_id === $p['product']
                && DigitalProduct::findOrFail($grant->digital_product_id)->app_key === $app && $this->authorized($grant);
        } catch (\Throwable) { return false; }
    }
}
