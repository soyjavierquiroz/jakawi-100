<?php
// Test-only HTTP adapter: never mount in a production runtime.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') throw new RuntimeException('Isolated test DB required');
config(['app.url' => 'http://127.0.0.1:8108', 'session.secure' => false, 'media.disk' => 'public', 'media.imgproxy_url' => 'http://127.0.0.1:8108/__qa/img', 'media.imgproxy_key' => str_repeat('ab', 32), 'media.imgproxy_salt' => str_repeat('cd', 32)]);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
Illuminate\Support\Facades\Auth::guard()->setUser(App\Models\User::where('email', 'media-qa@example.test')->sole());
if ($request->path() === '__qa/persisted') {
    header('Content-Type: application/json');
    echo json_encode(['partner' => App\Models\Partner::where('slug', 'media-qa-partner')->sole()->only(['id', 'logo_path', 'cover_path']), 'landing' => App\Models\LandingPresentation::where('slug', 'media-qa-landing')->sole()->only(['hero_path', 'media_mode']), 'objects' => Illuminate\Support\Facades\Storage::disk('public')->allFiles()]);
    exit;
}
if (str_starts_with($request->path(), '__qa/img/')) {
    // Browser QA exercises actual normalized private test objects. The production
    // imgproxy URL/signing strategy is covered separately by backend tests.
    $path = urldecode($request->path());
    preg_match('#/rs:fill:(\d+):(\d+)/.*?/plain/s3://jakawi-media/(.+)$#', $path, $parts);
    if (! $parts || ! app(App\Services\MediaUploadService::class)->isMediaKey($parts[3])) { http_response_code(404); exit; }
    $bytes = Illuminate\Support\Facades\Storage::disk('public')->get($parts[3]);
    $image = imagecreatefromstring($bytes);
    $width = min((int) $parts[1], imagesx($image));
    $output = imagescale($image, $width);
    header('Content-Type: image/webp');
    imagewebp($output);
    exit;
}
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
