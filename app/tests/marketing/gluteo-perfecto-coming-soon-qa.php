<?php
require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('testing')||config('database.connections.pgsql.database')!=='jakawi_test'||config('database.connections.pgsql.host')!=='db-test')throw new RuntimeException('QA isolation required');
config(['jakawi.prelaunch_mode'=>true,'crm.enabled'=>false,'commercial.enabled'=>false]);
app(App\Services\GluteoPerfectoDraft::class)->create(false);
$result=app(App\Services\GluteoPerfectoVisibility::class)->publish(false);
App\Models\User::factory()->create(['name'=>'Editor QA','email'=>'coming-soon-editor@qa.invalid','password'=>Illuminate\Support\Facades\Hash::make('Isolated-ComingSoon-QA-2026'),'is_admin'=>true]);
echo json_encode($result);
