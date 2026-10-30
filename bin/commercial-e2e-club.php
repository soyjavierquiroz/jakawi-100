<?php

chdir('/var/www/html');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.pgsql.database') !== 'jakawi_club_e2e'
    || config('database.connections.pgsql.host') !== 'db-test') {
    throw new RuntimeException('Disposable CLUB database required');
}
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, DB};

if (($argv[1] ?? '') === 'prepare') {
    Artisan::call('migrate', ['--force' => true]);
    $referrer = new User(['name' => 'Isolated CLUB referrer', 'email' => 'referrer@example.test', 'password' => 'FixturePassword123']);
    $referrer->forceFill(['referral_code' => 'AB12CD34EF56'])->save();
    file_put_contents('/evidence/e2e-club-fixture.json', json_encode(['code' => $referrer->referral_code, 'uuid' => $referrer->uuid]));
    exit;
}
$events = json_decode(file_get_contents('/evidence/e2e-events.json'), true, 512, JSON_THROW_ON_ERROR);
$fixture = json_decode(file_get_contents('/evidence/e2e-club-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($events as $case => $event) {
    $stored = DB::table('commercial_events')->where('event_id', $event['event_id'])->sole();
    if ($stored->com_reference_id !== $event['com_reference']['id'] || $stored->partner_id !== $event['partner']['id']
        || $stored->landing !== ($event['origin']['landing'] ?? null) || $stored->campaign !== ($event['origin']['campaign'] ?? null)
        || $stored->conversion_status !== 'confirmed' || $stored->global_subject_id !== $event['global_subject_id']) throw new RuntimeException('Receiver provenance mismatch');
    if ($case === 'with_club_ref' && ($stored->club_referral_code !== $fixture['code'] || $stored->referrer_club_uuid !== $fixture['uuid'])) throw new RuntimeException('CLUB code not recognized');
    if ($case === 'without_club_ref' && ($stored->club_referral_code !== null || $stored->referrer_club_uuid !== null)) throw new RuntimeException('Receiver invented referrer');
}
if (DB::table('commercial_events')->count() !== 2 || DB::table('jobs')->count() !== 0 || User::count() !== 1) throw new RuntimeException('Receiver side effects mismatch');
$event = $events['with_club_ref'];
// Exercise the protected admin route with a fixture account, exclusively in this DB.
$admin = User::create(['name' => 'Isolated admin', 'email' => 'admin@example.test', 'password' => 'FixturePassword123']);
$admin->forceFill(['email_verified_at' => now(), 'activated_at' => now(), 'is_marketing_admin' => true])->save();
auth()->login($admin);
$request = Request::create('https://jakawi.club/admin/conversiones-com?partner='.$event['partner']['id'].'&campaign=isolated-e2e', 'GET', [], [], [], [
    'HTTP_ACCEPT' => 'text/html', 'HTTP_X_INERTIA' => 'true',
    'HTTP_X_INERTIA_VERSION' => (new App\Http\Middleware\HandleInertiaRequests)->version(Request::create('/')),
]);
$request->setLaravelSession(app('session')->driver());
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
$page = json_decode($response->getContent(), true);
$visible = collect($page['props']['events']['data'] ?? [])->firstWhere('event_id', $event['event_id']) ?? [];
if ($response->getStatusCode() !== 200 || ($visible['event_id'] ?? null) !== $event['event_id']
    || ($visible['landing'] ?? null) !== $event['origin']['landing'] || ($visible['conversion_status'] ?? null) !== 'confirmed'
    || ($visible['referral_verification'] ?? null) !== 'club_code_verified') {
    throw new RuntimeException('Admin panel did not expose received attribution: HTTP '.$response->getStatusCode());
}
$result = ['result' => 'PASS', 'environment' => 'isolated COM and CLUB databases, internal TLS network',
    'event_id' => $event['event_id'], 'panel_http' => 200, 'component' => $page['component'],
    'visible_attribution' => $visible, 'club_commercial_events' => 2, 'club_code_recognized' => true, 'without_club_ref_accepted' => true, 'club_crm_jobs' => 0,
    'production_events_created' => 0];
file_put_contents('/evidence/e2e-result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL;
