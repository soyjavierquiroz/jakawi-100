<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('app.env') !== 'testing' || config('database.connections.pgsql.database') !== 'jakawi_test' || config('database.connections.pgsql.host') !== 'db-test') throw new RuntimeException('Test DB required');
App\Models\User::factory()->create(['email'=>'publisher-qa@example.test','is_admin'=>true]);
$subject = App\Models\Experience::factory()->published()->create(['slug'=>'publisher-arcilla-qa','title'=>'Arcilla y manos — DEMO','cover_path'=>'qa-clay.svg']);
$subject->landingPresentations()->create(['name'=>'Old draft','slug'=>'publisher-old-draft','status'=>'DRAFT','default_scope'=>'NONE','publish_scope'=>'GUESTS','template_key'=>'OFFER_CONVERSION','marketing_theme'=>'LIGHT_CONVERSION','hero_layout'=>'OFFER_FIRST','density'=>'STANDARD','media_mode'=>'IMAGE','headline'=>'PREVIOUS SAVED COPY','editorial_version'=>1]);
$subject->landingPresentations()->create(['name'=>'Collision','slug'=>'arcilla-y-manos-demo-collision','status'=>'DRAFT','default_scope'=>'NONE']);
echo 'Seeded isolated fixtures';
