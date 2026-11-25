<?php
// Explicit release operation: update visual metadata only, preserving all editorial content.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\LandingPresentation;
use Illuminate\Support\Facades\DB;
DB::transaction(function (): void {
    $landing = LandingPresentation::where('slug', 'gluteo-perfecto')->lockForUpdate()->sole();
    if ($landing->subject->slug !== 'gluteo-perfecto' || $landing->status !== 'PUBLISHED' || count($landing->editorial_blocks) !== 11) throw new RuntimeException('Unexpected landing');
    $presets = json_decode(file_get_contents(resource_path('content/gluteo-perfecto-visual.json')), true, 512, JSON_THROW_ON_ERROR);
    $before = $landing->editorial_blocks;
    $after = array_map(fn (array $block): array => [...$block, ...$presets[$block['type']]], $before);
    $landing->update(['editorial_blocks' => $after]);
    echo json_encode(['before' => $before, 'after' => $after], JSON_THROW_ON_ERROR);
});
