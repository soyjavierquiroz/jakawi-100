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
use App\Models\{Benefit, Location, Membership, Partner, Redemption, User};
use App\Services\{PublicJourneyContinuation, RedemptionService};
use App\Services\Commercial\{CommercialClient, CommercialDispatcher};
use Illuminate\Support\Facades\{Artisan, DB};
use Illuminate\Http\Request;

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
$fixture = json_decode(file_get_contents('/evidence/e2e-club-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$operator = User::factory()->create();
$operator->partners()->attach($partner->id);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$events = [];
foreach (['with_club_ref', 'without_club_ref'] as $case) {
    auth()->logout();
    app('session')->flush();
    $user = User::factory()->create();
    Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'amount_paid' => 100]);
    $request = Request::create('https://jakawi.com/l/'.$landing->slug.($case === 'with_club_ref' ? '?club_ref='.$fixture['code'] : ''), 'GET');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== 200) throw new RuntimeException('Landing visit failed: '.$response->getStatusCode());
    // The visitor session becomes the customer's session; the operation service
    // is the real one. Feature tests additionally exercise actual login/signup HTTP.
    $request->setUserResolver(fn () => $user);
    auth()->login($user);
    app(PublicJourneyContinuation::class)->setLanding($landing);
    $redemption = app(RedemptionService::class)->start($user, $benefit, $location);
    $frozen = DB::table('commercial_conversion_origins')->where('redemption_id', $redemption->id)->value('attribution');
    if (app(CommercialDispatcher::class)->run() !== 0) throw new RuntimeException('Pending redemption emitted an event');
    // A different operator session may carry its own code. It must never win.
    auth()->logout();
    app('session')->flush();
    auth()->login($operator);
    $request = Request::create('https://jakawi.com/l/'.$landing->slug.'?club_ref=ZZ99YY88XX77', 'GET');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== 200) throw new RuntimeException('Operator landing failed');
    $request = Request::create('https://jakawi.com/partner/dossis43/validar', 'POST', ['code' => $redemption->code, 'pin' => '123456']);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== 200 || $redemption->fresh()->status !== 'confirmed') throw new RuntimeException('Operator PIN confirmation failed: '.$response->getStatusCode());
    if ($frozen !== DB::table('commercial_conversion_origins')->where('redemption_id', $redemption->id)->value('attribution')) throw new RuntimeException('Operator replaced original provenance');
    if (app(CommercialDispatcher::class)->run() !== 1) throw new RuntimeException('Expected one delivery');
    $row = DB::table('commercial_outbox')->where('event_id', 'com:redemption:'.$redemption->public_id.':confirmed')->sole();
    if ($row->status !== 'SENT' || $row->last_http_status !== 201) throw new RuntimeException('Delivery failed: '.$row->last_error_code);
    $event = json_decode($row->raw_body, true, 512, JSON_THROW_ON_ERROR);
    if ($case === 'with_club_ref' && ($event['referral']['club_referral_code'] ?? null) !== $fixture['code']) throw new RuntimeException('Missing visitor CLUB code');
    if ($case === 'without_club_ref' && isset($event['referral'])) throw new RuntimeException('Invented referral');
    $events[$case] = $event;
    $duplicate = app(CommercialClient::class)->send($row->raw_body, $row->event_id);
    if (! $duplicate['sent'] || $duplicate['http'] !== 200) throw new RuntimeException('Expected idempotent duplicate');
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
if ($counts !== [0, 1] || DB::table('commercial_outbox')->where('id', $row->id)->value('attempts') !== 2
    || DB::table('commercial_outbox')->where('id', $row->id)->value('status') !== 'SENT') {
    throw new RuntimeException('Concurrent recovery failed');
}
file_put_contents('/evidence/e2e-events.json', json_encode($events, JSON_THROW_ON_ERROR));
echo json_encode(['conversion' => 'PIN-confirmed redemption in isolated COM', 'event_id' => $row->event_id,
    'https_hmac_delivery' => 201, 'duplicate' => 200, 'concurrent_recovery' => $counts, 'real_live_records_created' => 0]).PHP_EOL;
