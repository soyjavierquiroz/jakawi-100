<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Partner,Location,Benefit,Experience};
$m=json_decode(file_get_contents($argv[1].'/manifest.json'),true);$checks=[];
foreach($m['items'] as $i){$p=Partner::whereSlug($i['key'])->sole();$l=Location::whereSlug($i['location']['slug'])->sole();$class=$i['domain']==='Benefit'?Benefit::class:Experience::class;$x=$class::whereSlug($i['product']['slug'])->sole();$landing=$x->landingPresentations()->sole();
 foreach($i['partner'] as $k=>$v)if($p->$k!=$v)throw new RuntimeException('Partner mismatch '.$k);
 foreach($i['location'] as $k=>$v){if($k==='partner_slug')continue;if($l->$k!=$v)throw new RuntimeException('Location mismatch '.$k);}
 if($l->partner_id!==$p->id||$l->city_id!==App\Models\City::whereSlug('cochabamba')->sole()->id||$l->hasRedemptionPin()||$x->status!=='draft'||$landing->status!=='DRAFT'||$landing->default_scope!=='NONE'||$landing->publish_scope!=='NONE')throw new RuntimeException('Unsafe relation/state');
 if($x instanceof Benefit){if($x->partner_id!==$p->id||$x->requiresMembership()||$x->isAvailable()||$x->locations()->sole()->id!==$l->id||$x->redemption_limit_per_member!==null||$x->estimated_savings!==null||App\Models\Redemption::where('benefit_id',$x->id)->exists())throw new RuntimeException('Benefit invariant');}
 else {if($x->partners()->sole()->id!==$p->id||$x->sessions()->exists()||$x->reservations()->exists()||$x->member_price!==null||$x->duration_minutes!==null||$x->operations_confirmed||$x->catalog_visibility!=='hidden'||$x->reservation_method!=='none'||$x->currency!=='BOB')throw new RuntimeException('Experience invariant');}
 if($x instanceof Experience && $x->regular_price!=='45.00')throw new RuntimeException('Price invariant');
 $checks[]=['key'=>$i['key'],'identity'=>'PASS','contacts'=>'PASS','location_and_city'=>'PASS','relations'=>'PASS','private_states'=>'PASS','no_operation'=>'PASS'];
}
echo json_encode(['status'=>'PASS','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
