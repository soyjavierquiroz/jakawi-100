<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') throw new RuntimeException('Isolated test DB required');
App\Models\User::factory()->create(['email' => 'media-qa@example.test', 'is_admin' => true]);
$partner = App\Models\Partner::factory()->create(['slug' => 'media-qa-partner', 'name' => 'Media QA Partner']);
$benefit = App\Models\Benefit::factory()->forPartner($partner)->create(['slug' => 'media-qa-benefit', 'title' => 'Media QA Benefit']);
$benefit->landingPresentations()->create(['name' => 'Media QA Landing', 'slug' => 'media-qa-landing', 'status' => 'DRAFT', 'default_scope' => 'NONE', 'media_mode' => 'IMAGE', 'template_key' => 'OFFER_CONVERSION', 'marketing_theme' => 'LIGHT_CONVERSION', 'editorial_version' => 1]);
foreach (['first' => [220, 80, 40], 'second' => [30, 130, 210]] as $name => $rgb) {
    $image = imagecreatetruecolor(1800, 1200);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
    imagejpeg($image, '/tmp/media-'.$name.'.jpg', 90);
    imagedestroy($image);
}
echo json_encode(['partner' => $partner->id, 'benefit' => $benefit->slug]);
