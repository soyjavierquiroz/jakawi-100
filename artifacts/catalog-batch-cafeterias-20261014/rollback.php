<?php
// Limited rollback. Dry-run by default; refuses changed rows or any new dependency.
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$base=$argv[1];$apply=($argv[2]??'')==='--apply';$rows=json_decode(file_get_contents($base.'/results.json'),true,flags:JSON_THROW_ON_ERROR);$snapshot=json_decode(file_get_contents($base.'/after.json'),true,flags:JSON_THROW_ON_ERROR);
DB::transaction(function()use($rows,$snapshot,$apply){
 foreach(array_reverse($rows) as $r){
 $tables=['partners'=>$r['partner_id'],'locations'=>$r['location_id'],($r['domain']==='Benefit'?'benefits':'experiences')=>$r['product_id'],'landing_presentations'=>$r['landing_id']];
 foreach($tables as $t=>$id){$current=(array)DB::table($t)->where('id',$id)->lockForUpdate()->first();$old=collect($snapshot[$t])->firstWhere('id',$id);unset($current['redemption_pin_hash']);if($current!=$old)throw new RuntimeException('Changed record; review before rollback: '.$t.' '.$id);}
 $class=$r['domain']==='Benefit'?App\Models\Benefit::class:App\Models\Experience::class;$x=$class::findOrFail($r['product_id']);
 if($x->status!=='draft'||$x->landingPresentations()->count()!==1)throw new RuntimeException('Product advanced');
 if($x instanceof App\Models\Experience){if($x->sessions()->exists()||$x->reservations()->exists())throw new RuntimeException('Operational dependency');}
 DB::table('landing_presentations')->where('id',$r['landing_id'])->delete();
 if($x instanceof App\Models\Benefit){$x->locations()->detach();}else{$x->partners()->detach();}
 $x->delete();App\Models\Location::findOrFail($r['location_id'])->delete();App\Models\Partner::findOrFail($r['partner_id'])->delete();
 }
 if(!$apply){DB::rollBack();echo "PASS: limited rollback validated and rolled back; no deletions persisted.\n";}else{echo "PASS: only exact batch records deleted.\n";}
});
