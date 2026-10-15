<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
DB::beginTransaction();DB::statement('SET TRANSACTION READ ONLY');
$r=[];foreach(['partners','locations','benefits','benefit_location','experiences','experience_partner','experience_sessions','experience_reservations','redemptions','landing_presentations','challenges','payments','settings'] as $t){if(!Illuminate\Support\Facades\Schema::hasTable($t))continue;$r[$t]=DB::table($t)->orderByRaw('1')->get()->map(function($x)use($t){$x=(array)$x;unset($x['redemption_pin_hash']);if($t==='redemptions'){ksort($x);return ['id'=>$x['id'],'status'=>$x['status'],'sha256'=>hash('sha256',json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];}return $x;})->all();}
$r['prelaunch']=config('jakawi.prelaunch_mode');echo json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);DB::rollBack();
