<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
if (config('app.env') !== 'testing' || config('database.connections.pgsql.database') !== 'jakawi_test' || config('database.connections.pgsql.host') !== 'db-test') throw new RuntimeException('Test DB required');
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
Illuminate\Support\Facades\Auth::guard()->setUser(App\Models\User::where('email','publisher-qa@example.test')->sole());
if ($request->path() === '__qa/persisted') {
    header('Content-Type: application/json');
    echo App\Models\LandingPresentation::where('subject_id', App\Models\Experience::where('slug', 'publisher-arcilla-qa')->sole()->id)->orderBy('id')->firstOrFail()->toJson();
    exit;
}
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
