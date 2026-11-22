<?php

use App\Models\AppSetting;
use App\Models\Benefit;
use App\Models\City;
use App\Models\DigitalProduct;
use App\Models\Experience;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

// Synthetic editorial fixtures for the isolated browser database only. No banking configuration.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') {
    throw new RuntimeException('Isolated QA required');
}
config(['jakawi.prelaunch_mode' => true, 'crm.enabled' => false, 'commercial.enabled' => false, 'meta.enabled' => false, 'mail.default' => 'array']);

$partner = Partner::where('slug', 'jakawi-digital')->sole();
foreach (['admin', 'owner', 'member'] as $role) {
    $users[$role] = User::factory()->create(['name' => 'UX QA '.$role, 'email' => 'ux-'.$role.'@qa.invalid', 'password' => Hash::make('Isolated-Ux-QA-2026'), 'is_admin' => $role === 'admin', 'theme_preference' => 'system']);
}
$physical = Partner::factory()->published()->create(['name' => 'Casa UX QA', 'slug' => 'ux-qa', 'category' => 'cafe']);
Location::factory()->published()->withPartner($physical)->create(['name' => 'Sede QA', 'city_id' => City::where('slug', 'cochabamba')->value('id')]);
$users['owner']->partners()->attach($physical, ['role' => 'owner']);
foreach (range(1, 3) as $i) {
    Benefit::factory()->published()->forPartner($physical)->create(['title' => 'Beneficio UX QA '.$i, 'slug' => 'beneficio-ux-qa-'.$i, 'applies_to_all_locations' => true, 'category' => 'cafe']);
}
// Local cover fixtures. Not commercial products or supplier materials.
foreach (range(1, 20) as $i) {
    $im = imagecreatetruecolor(800, 640);
    $bg = imagecolorallocate($im, 25 + ($i % 4) * 15, 75 + ($i % 3) * 20, 95 + ($i % 5) * 15);
    imagefill($im, 0, 0, $bg);
    $ink = imagecolorallocate($im, 230, 245, 240);
    imagefilledellipse($im, 550, 220, 350, 350, $ink);
    imagestring($im, 5, 55, 430, 'MATERIAL FICTICIO - QA '.$i, $ink);
    ob_start();
    imagejpeg($im, null, 80);
    $bytes = ob_get_clean();
    imagedestroy($im);
    $path = 'ux-qa/cover-'.$i.'.jpg';
    Storage::disk('public')->put($path, $bytes);
    DigitalProduct::create(['partner_id' => $partner->id, 'name' => sprintf('Guía creativa QA %02d', $i), 'slug' => 'guia-ux-qa-'.$i, 'status' => 'published', 'cover_path' => $path, 'short_description' => 'Material ficticio para explorar ideas y nuevos hábitos.', 'description' => $i === 7 ? 'Contenido especial: brújula' : 'Lectura y ejercicios de creatividad.', 'commercial_content' => 'Contenido exclusivamente de prueba.', 'public_price' => '20.00', 'member_price' => '12.50', 'currency' => 'BOB', 'access_terms' => 'Uso exclusivo QA.', 'support_information' => 'ux@qa.invalid']);
}
DigitalProduct::create(['partner_id' => $partner->id, 'name' => 'Borrador invisible QA', 'slug' => 'borrador-ux-qa', 'status' => 'draft']);
$experience = Experience::factory()->published()->create(['slug' => 'ux-video', 'title' => 'Video UX QA', 'catalog_visibility' => 'upcoming', 'operations_confirmed' => false]);
$experience->syncPartnersWithRoles([['partner_id' => $physical->id, 'role' => 'organizer']]);
$experience->landingPresentations()->create(['name' => 'Video QA', 'slug' => 'ux-video', 'status' => 'PUBLISHED', 'media_mode' => 'VSL', 'vsl' => ['source' => ['type' => 'mp4', 'url' => 'https://media.ux.example.test/test.mp4'], 'framing' => 'native-vertical']]);
AppSetting::updateOrCreate(['key' => 'show_demo_banner'], ['value' => ['enabled' => false]]);
if (AppSetting::where('key', 'digital_collection_v1')->exists()) {
    throw new RuntimeException('Unexpected collection config');
}
echo json_encode(['products' => 20, 'drafts' => 1, 'prelaunch' => true, 'collection_configured' => false]);
