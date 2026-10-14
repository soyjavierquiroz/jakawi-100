<?php
// One-product inventory operation. Run from Laravel's root with validated editorial patch.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Experience, Partner, User};
use App\Services\LandingPresentationEditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Validator};

if (count($argv) !== 3) throw new RuntimeException('Usage: php configure-toctoc-experience.php product.json validated-landing-patch.json');
$product = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$patch = json_decode(file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR);
if ($product['slug'] !== 'toctoccafe-descubre-el-cafe-boliviano' || $product['status'] !== 'draft' || $product['reservation_method'] !== 'none') throw new RuntimeException('Draft-only Toc Toc operation');
$result = DB::transaction(function () use ($product, $patch) {
    $partner = Partner::whereSlug('toctoccafe')->lockForUpdate()->sole();
    if ($partner->name !== 'Toc Toc Café Gourmet') throw new RuntimeException('Unexpected partner');
    $admin = User::where('is_admin', true)->orderBy('id')->firstOrFail();
    if (Experience::whereSlug($product['slug'])->exists()) throw new RuntimeException('Product already exists; refusing to overwrite');
    Validator::make($product, App\Support\ExperienceRules::experience())->validate();
    $experience = Experience::create($product + ['created_by_user_id' => $admin->id, 'reviewed_by_user_id' => $admin->id, 'reviewed_at' => now(), 'cover_path' => $partner->cover_path]);
    $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);
    // Use the existing Publisher's authoritative validation and draft persistence.
    $request = Request::create('/admin/experiencias/'.$experience->slug.'/landings', 'POST', $patch + ['slug' => $experience->slug, 'default_scope' => 'ALL']);
    $request->setUserResolver(fn () => $admin);
    $route = new Illuminate\Routing\Route('POST', '/admin/experiencias/{experience}/landings', []);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    app(App\Http\Controllers\AdminLandingPresentationController::class)->save($request, $experience->slug);
    $landing = $experience->landingPresentations()->sole();
    if ($landing->status !== 'DRAFT' || $landing->default_scope !== 'NONE' || $experience->sessions()->exists()) throw new RuntimeException('Unsafe inventory state');
    $brief = app(LandingPresentationEditor::class)->context($experience, $request)['aiBrief'];
    // A draft has no session from which the builder can infer city. Use the real Partner location.
    $brief['city'] = $partner->locations()->where('is_primary', true)->sole()->city;
    return ['experience_id' => $experience->id, 'slug' => $experience->slug, 'partner_id' => $partner->id, 'landing_id' => $landing->id, 'status' => $experience->status, 'landing_status' => $landing->status, 'brief' => $brief];
});
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
