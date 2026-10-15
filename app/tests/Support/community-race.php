<?php

use App\Models\City;
use App\Models\User;
use App\Services\CityCommunity;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment() !== 'testing' || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') {
    exit(78);
}
$request = Request::create('/');
$session = app('session')->driver();
$session->setId($argv[3]);
$session->start();
$request->setLaravelSession($session);
$app->instance('request', $request);
$request->setUserResolver(fn () => User::findOrFail($argv[1]));
// Both children announce readiness; neither enters the critical section early.
file_put_contents($argv[4].'.'.$argv[5], 'ready');
$deadline = microtime(true) + 10;
while (! file_exists($argv[4].'.go')) {
    if (microtime(true) > $deadline) {
        exit(79);
    }
    usleep(10000);
}
$result = app(CityCommunity::class)->interact($request, City::where('slug', $argv[2])->firstOrFail(), 'presented', 'home');
echo json_encode($result);
