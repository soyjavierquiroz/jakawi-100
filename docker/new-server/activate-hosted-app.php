<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::transaction(function () {
    $p=App\Models\DigitalProduct::lockForUpdate()->findOrFail(1);
    if ($p->slug!=='gluteo-perfecto' || $p->partner->slug!=='jakawi-digital' || $p->public_price!=='49.00' || $p->member_price!=='29.00') throw new RuntimeException('Product baseline changed');
    if (App\Models\DigitalOrder::where('digital_product_id',1)->whereIn('status',['confirmed','refunded'])->exists()) throw new RuntimeException('Inspect prior paid PDF obligations before switching delivery');
    $p->update(['delivery_type'=>'hosted_app','app_key'=>'gluteos']);
    if (!app(App\Services\HostedApps::class)->ready($p)) throw new RuntimeException('STORE unavailable or unprotected');
    app(App\Services\DigitalSaleAvailability::class)->configure($p,true,true,1);
    if (!app(App\Services\DigitalSaleAvailability::class)->canSell($p)) throw new RuntimeException('Sale requirements not satisfied');
    if (!app(App\Services\HostedApps::class)->authorized(App\Models\DigitalAccessGrant::findOrFail(1))) throw new RuntimeException('Existing Javier access lost');
    if (App\Models\DigitalGift::findOrFail(2)->status!=='pending') throw new RuntimeException('Pending gift changed');
    App\Models\AuditLog::create(['actor_user_id'=>1,'action'=>'digital_delivery_changed_to_hosted_app','subject_type'=>App\Models\DigitalProduct::class,'subject_id'=>1,'metadata'=>['app_key'=>'gluteos','archived_pdf_sha256'=>$p->file_sha256,'authorization'=>'Owner requested APP replacement, existing grants retained']]);
});
echo "Existing product switched; prices, QR, PDF archive and grants preserved.\n";
