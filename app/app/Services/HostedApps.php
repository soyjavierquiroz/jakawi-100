<?php
namespace App\Services;

use App\Models\{DigitalAccessGrant, DigitalProduct};
use Illuminate\Support\Facades\{Cache, Http};

class HostedApps
{
    public function registered(DigitalProduct $product): bool
    {
        return $product->delivery_type === 'hosted_app' && filled(config('hosted_apps.secret'))
            && filled(config('hosted_apps.apps.'.$product->app_key.'.entry'));
    }

    public function ready(DigitalProduct $product): bool
    {
        if (! $this->registered($product)) return false;
        return Cache::store('database')->remember('hosted-app-health:'.$product->app_key, 30, function () use ($product) {
            try {
                $r = Http::withToken(config('hosted_apps.secret'))->timeout(20)->get(config('hosted_apps.apps.'.$product->app_key.'.health'));
                return $r->ok() && $r->json('protected') === true && $r->json('functional') === true;
            } catch (\Throwable) { return false; }
        });
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
