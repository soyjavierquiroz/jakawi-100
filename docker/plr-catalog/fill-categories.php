<?php
// One-time category-only enrichment; only registered imports, never owner-selected categories.
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$plan=json_decode(file_get_contents('php://stdin'),true,512,JSON_THROW_ON_ERROR);
$results=Illuminate\Support\Facades\DB::transaction(function() use($plan) {
    $db=Illuminate\Support\Facades\DB::class;$results=[];
    foreach($plan as $item) {
        $record=App\Models\AppSetting::where('key','hosted_app_import:'.$item['slug'])->firstOrFail()->value;
        if($record['product_id']!==$item['product_id'] || $record['vendor_id']!==$item['vendor_id'] || $record['package_sha256']!==$item['package_sha256']) throw new RuntimeException('Import record mismatch');
        $p=App\Models\DigitalProduct::whereKey($item['product_id'])->lockForUpdate()->firstOrFail();
        if($p->id===1 || $p->app_key!==$item['slug'] || $p->delivery_type!=='hosted_app' || $p->partner->slug!=='jakawi-digital') throw new RuntimeException('Not an imported APP');
        if(!in_array($item['category'],config('jakawi.digital_categories'),true)) throw new RuntimeException('Unknown category');
        if(blank($p->category)) {
            $db::table('digital_products')->where('id',$p->id)->update(['category'=>$item['category']]);
            $results[]=['product_id'=>$p->id,'category'=>$item['category'],'result'=>'filled'];
        } else $results[]=['product_id'=>$p->id,'category'=>$p->category,'result'=>'preserved'];
    }
    return $results;
});
echo json_encode($results);
