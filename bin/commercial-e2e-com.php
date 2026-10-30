<?php

// Runs only on the internal disposable network; jakawi.club resolves to test TLS.
chdir('/var/www/html');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.pgsql.database') !== 'jakawi_commercial_e2e'
    || config('database.connections.pgsql.host') !== 'db-test') {
    throw new RuntimeException('Disposable COM database required');
}
use App\Models\{Benefit, Location, Membership, Partner, User};
use App\Services\{PublicJourneyContinuation, RedemptionService};
use App\Services\Commercial\{CommercialClient, CommercialDispatcher};
use Illuminate\Support\Facades\{Artisan, DB};

if (($argv[1] ?? '') === '--dispatch-only') {
    echo app(CommercialDispatcher::class)->run();
    exit;
}

Artisan::call('migrate', ['--force' => true]);
$partner = Partner::factory()->published()->create(['slug' => 'dossis43', 'name' => 'Dossis 43 | Coffee']);
$location = Location::factory()->published()->withPartner($partner)->create();
$location->setRedemptionPin('123456');
$location->save();
$benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
$landing = $benefit->landingPresentations()->create(['name' => 'Pilot fixture', 'slug' => config('commercial.pilot_landing'), 'status' => 'PUBLISHED', 'campaign_key' => 'isolated-e2e']);
$user = User::factory()->create();
Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'amount_paid' => 100]);
$request = app('request');
$request->setUserResolver(fn () => $user);
$request->setLaravelSession(app('session')->driver());
app(PublicJourneyContinuation::class)->setLanding($landing);
$service = app(RedemptionService::class);
$redemption = $service->start($user, $benefit, $location);
assert(DB::table('commercial_outbox')->count() === 0);
$service->confirm($redemption->code, '123456');
if (app(CommercialDispatcher::class)->run() !== 1) {
    throw new RuntimeException('Expected one delivery');
}
$row = DB::table('commercial_outbox')->sole();
if ($row->status !== 'SENT' || $row->last_http_status !== 201) {
    throw new RuntimeException('Delivery failed: '.$row->last_error_code);
}
$duplicate = app(CommercialClient::class)->send($row->raw_body, $row->event_id);
if (! $duplicate['sent'] || $duplicate['http'] !== 200) {
    throw new RuntimeException('Expected idempotent duplicate');
}
// Lost acknowledgement after remote commit: two local dispatchers race to recover.
DB::table('commercial_outbox')->where('id', $row->id)->update(['status' => 'RETRY', 'next_attempt_at' => now()->subSecond()]);
$children = [];
for ($i = 0; $i < 2; $i++) {
    $process = proc_open([PHP_BINARY, '-d', 'curl.cainfo=/test/tls.crt', '-d', 'openssl.cafile=/test/tls.crt', __FILE__, '--dispatch-only'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $children[] = [$process, $pipes];
}
$counts = [];
foreach ($children as [$process, $pipes]) {
    $counts[] = (int) stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException($error);
}
sort($counts);
if ($counts !== [0, 1] || DB::table('commercial_outbox')->value('attempts') !== 2
    || DB::table('commercial_outbox')->value('status') !== 'SENT') {
    throw new RuntimeException('Concurrent recovery failed');
}
file_put_contents('/evidence/e2e-event.json', $row->raw_body);
echo json_encode(['conversion' => 'PIN-confirmed redemption in isolated COM', 'event_id' => $row->event_id,
    'https_hmac_delivery' => 201, 'duplicate' => 200, 'concurrent_recovery' => $counts, 'real_live_records_created' => 0]).PHP_EOL;
