<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') throw new RuntimeException('Isolated tests only');
$city=App\Models\City::updateOrCreate(['slug'=>'cochabamba'],['name'=>'Cochabamba','country_code'=>'BO','status'=>App\Models\City::ACTIVE,'community_enabled'=>true,'community_name'=>'JAKAWI Cochabamba','community_invite_url'=>'https://chat.whatsapp.com/ABCDEFGHIJKLMNOPQRSTUV']);
foreach(['free','pioneer','standard','expired'] as $type) {
 $u=App\Models\User::factory()->create(['name'=>'Cuenta de prueba '.$type,'email'=>$type.'@example.test','password'=>'MiJakawi-test-only-2026!']);
 if($type!=='free') App\Models\Membership::create(['user_id'=>$u->id,'type'=>$type==='pioneer'?'pioneer':'standard','status'=>'active','starts_at'=>now()->subYears(2),'ends_at'=>$type==='standard'?now()->addMonths(6):now()->subYear(),'amount_paid'=>100]);
}
$p=App\Models\Partner::factory()->published()->create(['name'=>'Partner de prueba']);
$l=App\Models\Location::factory()->published()->withPartner($p)->create();$l->setRedemptionPin('123456');$l->save();
foreach(['authenticated'=>'Beneficio con tu cuenta','public'=>'Beneficio de membresía'] as $mode=>$title) App\Models\Benefit::factory()->published()->forPartner($p)->create(['title'=>$title,'access_mode'=>$mode,'applies_to_all_locations'=>true,'image_path'=>'qa-mi-benefit.png','featured'=>true]);
$challenge=App\Models\Challenge::create(['slug'=>'reto-de-prueba','title'=>'Descubre tu ciudad','status'=>'open','review_status'=>'APPROVED','qualification_type'=>'MANUAL','selection_type'=>'MANUAL','reward_type'=>'MANUAL_PRIZE']);
App\Models\ChallengeParticipation::create(['challenge_id'=>$challenge->id,'user_id'=>App\Models\User::where('email','standard@example.test')->sole()->id,'qualification_status'=>'pending','selection_status'=>'pending']);
echo "Synthetic accounts and catalog ready.\n";
