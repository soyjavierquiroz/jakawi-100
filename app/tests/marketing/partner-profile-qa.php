<?php
// Isolated fixture + real HTTP kernel. No authentication injection or application-route changes in production.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
if (config('app.env') !== 'testing' || config('database.connections.pgsql.database') !== 'jakawi_test' || config('database.connections.pgsql.host') !== 'db-test') throw new RuntimeException('Isolated jakawi_test required');

if (PHP_SAPI !== 'cli-server') {
    if (($argv[1] ?? '') === 'clean') {
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
            $partner->users()->detach(); $partner->delete();
        }
        App\Models\User::whereIn('email', ['partner-owner-qa@example.test', 'partner-existing-qa@example.test', 'partner-browser-new@example.test', 'partner-browser-direct@example.test'])->delete();
        echo json_encode(['fixture_partners' => App\Models\Partner::whereIn('slug', ['dosis43', 'empty-brand-qa'])->count(), 'fixture_users' => App\Models\User::where('email', 'like', 'partner-%@example.test')->count()]);
        exit;
    }
    $partner = App\Models\Partner::factory()->published()->create(['slug' => 'dosis43', 'name' => 'DOSIS 43 — QA', 'category' => 'cafe', 'description' => 'Café para compartir en Cochabamba. Perfil de evaluación aislada.', 'logo_path' => 'partner-profile-qa-logo.svg', 'cover_path' => 'partner-profile-qa-cover.svg']);
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
    echo 'Seeded isolated DOSIS 43 — QA'; exit;
}

config(['jakawi.prelaunch_mode' => true, 'session.secure' => false]);
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
$response = $kernel->handle($request); $response->send(); $kernel->terminate($request, $response);
