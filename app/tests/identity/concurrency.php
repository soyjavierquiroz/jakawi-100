<?php

// Run only with bin/jakawi-identity-test php tests/identity/concurrency.php.
use App\Services\Identity\IdentityAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test'
    || config('database.connections.pgsql.database') !== 'jakawi_test') {
    throw new RuntimeException('REFUSING: identity concurrency test requires the disposable database.');
}
config(['identity.club_secret' => str_repeat('s', 64), 'identity.club_email_proof_secret' => str_repeat('p', 64), 'crm.enabled' => false]);
if (($argv[1] ?? '') === 'worker') {
    $data = json_decode(file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR);
    echo json_encode(app(IdentityAuthority::class)->handoff($data, hash('sha256', json_encode($data))));
    exit;
}
function requestData(string $email, string $club): array
{
    $id = (string) Str::uuid();
    $claims = ['iss' => 'https://jakawi.club', 'aud' => 'https://jakawi.com', 'sub' => $club, 'email' => $email,
        'handoff_id' => $id, 'jti' => (string) Str::uuid(), 'method' => 'email_activation_token',
        'activation_ref' => 'isolated-concurrency-test', 'activated_at' => now()->subHour()->toISOString(), 'iat' => time(), 'exp' => time() + 240];
    $encoded = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

    return ['contract' => IdentityAuthority::CONTRACT, 'source_app' => 'club', 'handoff_id' => $id, 'club_user_id' => $club,
        'name' => 'Concurrent identity test', 'email' => $email,
        'email_activation_proof' => $encoded.'.'.hash_hmac('sha256', "jakawi-email-activation-v1\n".$encoded, config('identity.club_email_proof_secret')),
        'consent' => ['purpose' => 'identity_link', 'version' => '1', 'accepted_at' => now()->toISOString()]];
}
function race(array $requests): array
{
    $processes = [];
    $files = [];
    try {
        foreach ($requests as $request) {
            $path = tempnam(sys_get_temp_dir(), 'identity-test-');
            chmod($path, 0600);
            file_put_contents($path, json_encode($request));
            $files[] = $path;
            $process = new Process([PHP_BINARY, __FILE__, 'worker', $path]);
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Concurrent worker failed: '.$process->getErrorOutput());
            }
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
$email = 'race-'.Str::uuid().'@example.test';
$request = requestData($email, (string) Str::uuid());
$results = race([$request, $request, $request]);
if ($results[0] != $results[1] || $results[1] != $results[2] || DB::table('users')->where('email', $email)->count() !== 1) {
    throw new RuntimeException('Same-handoff concurrency failed.');
}
$email2 = 'race-'.Str::uuid().'@example.test';
$requests = array_map(fn () => requestData($email2, (string) Str::uuid()), range(1, 3));
$results = race($requests);
$statuses = array_column($results, 'status');
sort($statuses);
if ($statuses !== ['AUTH_REQUIRED', 'AUTH_REQUIRED', 'PROVISIONED'] || DB::table('users')->where('email', $email2)->count() !== 1) {
    throw new RuntimeException('Same-email concurrency failed.');
}
echo json_encode(['same_handoff_workers' => 3, 'same_email_workers' => 3, 'duplicate_users' => 0, 'result' => 'PASS'], JSON_PRETTY_PRINT).PHP_EOL;
