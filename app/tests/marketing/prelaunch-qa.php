<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
if (config('app.env') !== 'testing' || config('database.connections.pgsql.database') !== 'jakawi_test' || config('database.connections.pgsql.host') !== 'db-test') throw new RuntimeException('Isolated test DB required');
if (PHP_SAPI !== 'cli-server') {
$admin = App\Models\User::where('email', 'prelaunch-qa@example.test')->first() ?? App\Models\User::factory()->create(['email'=>'prelaunch-qa@example.test', 'is_admin'=>true]);
$partner = App\Models\Partner::where('slug', 'prelaunch-place-qa')->first() ?? App\Models\Partner::factory()->published()->create(['name'=>'Lugar de evaluación', 'slug'=>'prelaunch-place-qa']);
$benefit = App\Models\Benefit::where('slug', 'prelaunch-benefit-qa')->first() ?? App\Models\Benefit::factory()->published()->forPartner($partner)->create(['title'=>'Beneficio para evaluar', 'slug'=>'prelaunch-benefit-qa','access_mode'=>'PUBLIC']);
$benefit->landingPresentations()->firstOrCreate(['name'=>'Evaluación aislada', 'slug'=>'prelaunch-benefit-qa','status'=>'PUBLISHED','default_scope'=>'NONE','publish_scope'=>'ALL','headline'=>'MÁS RAZONES PARA VIVIR TU CIUDAD.','subheadline'=>'Una propuesta para evaluar el próximo JAKAWI.']);


$benefit=App\Models\Benefit::where('slug','prelaunch-benefit-qa')->sole();
$location=App\Models\Location::where('slug','prelaunch-location-qa')->first() ?? Database\Factories\LocationFactory::new()->published()->withPartner($benefit->partner)->create(['slug'=>'prelaunch-location-qa','name'=>'Lugar de evaluación · Cochabamba','city_id'=>App\Models\City::where('slug','cochabamba')->sole()->id]);
$location->setRedemptionPin('123456');$location->save();$benefit->locations()->syncWithoutDetaching([$location->id]);
echo 'Isolated location added';

    exit;
}
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
config(['jakawi.prelaunch_mode'=>!$request->query('normal'), 'jakawi.analytics.enabled'=>false,'session.secure'=>false]);
if ($request->is('admin*', 'l/*/activar')) {
 Illuminate\Support\Facades\Auth::guard()->setUser(App\Models\User::where('email','prelaunch-qa@example.test')->sole());
}
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
