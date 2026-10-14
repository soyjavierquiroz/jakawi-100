<?php
// Isolated fixture + real HTTP kernel. No authentication injection or application-route changes in production.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
if (config('app.env') !== 'testing' || config('database.connections.pgsql.database') !== 'jakawi_test' || config('database.connections.pgsql.host') !== 'db-test') throw new RuntimeException('Isolated jakawi_test required');

config(['media.disk' => 'public', 'media.imgproxy_url' => 'http://127.0.0.1:8098/__qa/img', 'media.imgproxy_key' => str_repeat('ab', 32), 'media.imgproxy_salt' => str_repeat('cd', 32)]);

if (PHP_SAPI !== 'cli-server') {
    if (($argv[1] ?? '') === 'clean') {
        foreach (['instagram-only-qa', 'unsafe-socials-qa'] as $slug) { $social = App\Models\Partner::whereSlug($slug)->first(); if ($social) { App\Models\AttributionTouch::where('acquisition_partner_id', $social->id)->delete(); $social->delete(); } }
        $empty = App\Models\Partner::whereSlug('empty-brand-qa')->first();
        if ($empty) { $empty->locations()->delete(); App\Models\AttributionTouch::where('acquisition_partner_id', $empty->id)->delete(); $empty->delete(); }
        $partner = App\Models\Partner::whereSlug('dosis43')->first();
        if ($partner) {
            $benefit = $partner->benefits()->first();
            if ($benefit) { $benefit->landingPresentations()->delete(); $benefit->delete(); }
            foreach ($partner->experiences()->get() as $experience) { $experience->sessions()->delete(); $experience->partners()->detach(); $experience->delete(); }
            $partner->challenges()->delete(); $partner->locations()->delete();
            App\Models\ReferralRelationship::where('acquisition_partner_id', $partner->id)->delete();
            App\Models\AttributionTouch::where('acquisition_partner_id', $partner->id)->delete();
            Illuminate\Support\Facades\Storage::disk('public')->deleteDirectory('partners/'.$partner->id.'/logo');
            $partner->users()->detach(); $partner->delete();
        }
        $ledger = '/tmp/partner-identity-upload-keys.json';
        foreach (json_decode(@file_get_contents($ledger) ?: '[]', true) as $key) if (app(App\Services\MediaUploadService::class)->isTemporaryKey($key)) Illuminate\Support\Facades\Storage::disk('public')->delete($key);
        if (is_file($ledger)) unlink($ledger);
        App\Models\User::whereIn('email', ['partner-admin-qa@example.test', 'partner-owner-qa@example.test', 'partner-existing-qa@example.test', 'partner-browser-new@example.test', 'partner-browser-direct@example.test'])->delete();
        echo json_encode(['fixture_partners' => App\Models\Partner::whereIn('slug', ['dosis43', 'empty-brand-qa', 'instagram-only-qa', 'unsafe-socials-qa'])->count(), 'fixture_users' => App\Models\User::where('email', 'like', 'partner-%@example.test')->count()]);
        exit;
    }
    $partner = App\Models\Partner::factory()->published()->create(['slug' => 'dosis43', 'name' => 'DOSIS 43 — QA', 'instagram' => 'https://www.instagram.com/qa/', 'tiktok' => 'https://www.tiktok.com/@qa', 'facebook' => 'https://www.facebook.com/qa', 'website' => 'https://qa.example.test', 'whatsapp' => '+59171234567', 'category' => 'cafe', 'description' => 'Café para compartir en Cochabamba. Perfil de evaluación aislada.', 'logo_path' => 'partner-profile-qa-logo.svg', 'cover_path' => 'partner-profile-qa-cover.svg']);
    App\Models\User::factory()->create(['email' => 'partner-admin-qa@example.test', 'is_admin' => true]);
    $owner = App\Models\User::factory()->create(['email' => 'partner-owner-qa@example.test']); $partner->users()->attach($owner, ['role' => 'owner']);
    App\Models\User::factory()->create(['email' => 'partner-existing-qa@example.test', 'name' => 'Usuario existente QA']);
    $location = App\Models\Location::factory()->published()->withPartner($partner)->create(['slug' => 'dosis-location-qa', 'name' => 'DOSIS Centro — QA', 'city_id' => App\Models\City::whereSlug('cochabamba')->sole()->id, 'zone' => 'Centro', 'address' => 'Dirección de evaluación QA', 'maps_url' => 'https://maps.example.test/qa']);
    $location->setRedemptionPin('123456'); $location->save();
    $benefit = App\Models\Benefit::factory()->published()->forPartner($partner)->create(['slug' => 'dosis-benefit-qa', 'title' => 'Café para compartir — QA', 'short_description' => 'Un café de cortesía con tu consumo de evaluación.', 'estimated_savings' => 15, 'applies_to_all_locations' => true, 'image_path' => 'partner-profile-qa-coffee.svg', 'access_mode' => 'public']);
    $benefit->landingPresentations()->create(['name' => 'Partner Benefit — QA', 'slug' => 'dosis-benefit-landing-qa', 'status' => 'PUBLISHED', 'default_scope' => 'ALL', 'publish_scope' => 'ALL', 'template_key' => 'OFFER_CONVERSION', 'marketing_theme' => 'LIGHT_CONVERSION', 'media_mode' => 'IMAGE', 'hero_layout' => 'OFFER_FIRST', 'density' => 'STANDARD', 'headline' => 'COMPARTE TU PRÓXIMO CAFÉ.', 'subheadline' => 'Disfruta este café de evaluación con DOSIS 43 — QA.', 'hero_path' => 'partner-profile-qa-coffee.svg']);
    $experience = App\Models\Experience::factory()->published()->create(['slug' => 'dosis-experience-qa', 'title' => 'Cata de café — QA', 'short_description' => 'Descubre los aromas del café.', 'regular_price' => 50, 'member_price' => 35, 'currency' => 'BOB', 'image_path' => 'partner-profile-qa-coffee.svg']);
    $experience->partners()->attach($partner, ['role' => 'organizer']);
    App\Models\ExperienceSession::factory()->for($experience)->upcoming()->create(['location_id' => $location->id, 'starts_at' => now()->addDays(7)]);
    App\Models\Challenge::create(['partner_id' => $partner->id, 'slug' => 'dosis-challenge-qa', 'title' => 'Comparte tu café — QA', 'description' => 'Comparte tu foto del café.', 'status' => 'open', 'review_status' => 'APPROVED', 'reward_type' => 'JP', 'reward_jp_amount' => 25, 'ends_at' => now()->addDays(14), 'hero_path' => 'partner-profile-qa-coffee.svg']);
    $empty = App\Models\Partner::factory()->published()->create(['slug' => 'empty-brand-qa', 'name' => 'Marca local QA', 'description' => 'Hay días en los que solo necesitas un buen café para seguir adelante. Café para llevar y bebidas de cafetería para acompañar tus recorridos por la ciudad. Un lugar de evaluación con descripción extensa para validar el perfil sin medios.', 'category' => 'cafe', 'logo_path' => null, 'cover_path' => null]);
    App\Models\Location::factory()->published()->withPartner($empty)->create(['name' => 'Lugar sin medios QA', 'city' => 'Cochabamba']);
    $image = imagecreatetruecolor(800, 800); ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
    $file = Illuminate\Http\UploadedFile::fake()->createWithContent('old-logo.png', $png);
    $partner->update(['logo_path' => app(App\Services\MediaUploadService::class)->store($file, 'partners', $partner->id, 'logo')]);
    App\Models\Partner::factory()->published()->create(['slug' => 'instagram-only-qa', 'name' => 'Instagram QA', 'instagram' => 'https://www.instagram.com/only-qa/']);
    App\Models\Partner::factory()->published()->create(['slug' => 'unsafe-socials-qa', 'name' => 'Unsafe QA', 'instagram' => 'javascript:alert(1)', 'facebook' => 'data:text/html,unsafe', 'tiktok' => 'not-a-url', 'website' => null]);
    echo 'Seeded isolated DOSIS 43 — QA'; exit;
}

config(['jakawi.prelaunch_mode' => true, 'session.secure' => false]);
Illuminate\Support\Facades\Route::get('/__qa/img/{path}', function (string $path) {
    $key = substr($path, strpos($path, 's3://jakawi-media/') + strlen('s3://jakawi-media/'));
    abort_unless(app(App\Services\MediaUploadService::class)->isMediaKey($key), 404);
    return Illuminate\Support\Facades\Storage::disk('public')->response($key);
})->where('path', '.*');
Illuminate\Support\Facades\Route::middleware('web')->get('/__qa/identity', function () {
    $partner = App\Models\Partner::whereSlug('dosis43')->sole();
    $disk = Illuminate\Support\Facades\Storage::disk('public');
    $info = getimagesizefromstring($disk->get($partner->logo_path));
    return response()->json(['id' => $partner->id, 'logo_path' => $partner->logo_path, 'cover_path' => $partner->cover_path, 'width' => $info[0], 'height' => $info[1], 'mime' => $info['mime'], 'masters' => $disk->allFiles('partners/'.$partner->id.'/logo')]);
});
// Read-only test inspection through the real session middleware; no forced users or synthetic auth.
Illuminate\Support\Facades\Route::middleware('web')->get('/__qa/state', function (Illuminate\Http\Request $request) {
    $partner = App\Models\Partner::whereSlug('dosis43')->sole();
    return response()->json([
        'partner_id' => $partner->id,
        'user_id' => $request->user()?->id,
        'pending' => $request->session()->get('attribution_touch_ids', []),
        'users' => App\Models\User::whereIn('email', ['partner-existing-qa@example.test', 'partner-browser-new@example.test', 'partner-browser-direct@example.test'])->get()->map(fn ($user) => ['id' => $user->id, 'email' => $user->email, 'relationship' => App\Models\ReferralRelationship::where('referred_user_id', $user->id)->first()?->only(['acquisition_partner_id', 'referrer_user_id', 'referral_code'])]),
        'rewards' => App\Models\RewardTransaction::count(),
        'redemptions' => App\Models\Redemption::count(),
        'reservations' => App\Models\ExperienceReservation::count(),
        'participations' => App\Models\ChallengeParticipation::count(),
    ]);
});
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
if ($request->path() === 'media/uploads' && $response->getStatusCode() === 200) {
    $token = json_decode($response->getContent(), true)['token'];
    $key = json_decode(Illuminate\Support\Facades\Crypt::decryptString($token), true)['key'];
    $ledger = '/tmp/partner-identity-upload-keys.json';
    $keys = json_decode(@file_get_contents($ledger) ?: '[]', true); $keys[] = $key; file_put_contents($ledger, json_encode($keys));
}
$response->send(); $kernel->terminate($request, $response);
