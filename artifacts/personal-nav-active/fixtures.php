<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.host')!=='db-test' || config('database.connections.pgsql.database')!=='jakawi_test') throw new RuntimeException('Isolated database only');
App\Models\User::factory()->create(['name'=>'Cuenta de prueba','email'=>'navigation@example.test','password'=>'Navigation-QA-only-2026!']);
echo "Synthetic navigation account ready.\n";
