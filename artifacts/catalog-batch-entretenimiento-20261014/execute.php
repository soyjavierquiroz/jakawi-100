<?php
// Scoped data operation using Catalog V2 and the existing Publisher; no runtime deployment.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Partner,Location,Benefit,Experience,User};
use Illuminate\Support\Facades\{Artisan,DB,Validator};
use Illuminate\Http\Request;
$base=$argv[1]; $apply=($argv[2]??'')==='--apply';
$manifest=json_decode(file_get_contents($base.'/manifest.json'),true,flags:JSON_THROW_ON_ERROR);
$admin=User::where('is_admin',true)->orderBy('id')->firstOrFail();
$city=App\Models\City::where('slug','cochabamba')->sole();
$results=[];
foreach($manifest['items'] as $item){
 $key=$item['key'];$class=$item['domain']==='Benefit'?Benefit::class:Experience::class;
 $normalize=fn($v)=>mb_strtolower(preg_replace('/[^\pL\pN]/u','',Illuminate\Support\Str::ascii((string)$v)));
 $matches=Partner::all()->filter(function($p)use($normalize,$item,$key){
  foreach(['slug','name','phone','email','instagram','facebook','tiktok','website'] as $f){$v=$item['partner'][$f]??null;if($v && $normalize($p->$f)===$normalize($v))return true;}return false;
 });
 if($matches->count()>1)throw new RuntimeException('Ambiguous partner identity '.$key);
 if($matches->count() && $matches->first()->slug!==$key)throw new RuntimeException('Existing identity needs mapping '.$key);
 $existing=$class::whereSlug($item['product']['slug'])->first();
 if($existing){
  $landing=$existing->landingPresentations()->whereSlug($existing->slug)->sole();$partner=Partner::whereSlug($key)->sole();$location=Location::whereSlug($item['location']['slug'])->sole();
  foreach($item['partner'] as $field=>$value)if($partner->$field!=$value)throw new RuntimeException('Existing partner differs: '.$key.' '.$field);
  foreach($item['location'] as $field=>$value){if($field==='partner_slug')continue;if($location->$field!=$value)throw new RuntimeException('Existing location differs: '.$key.' '.$field);}
  $expectedPatch=json_decode(file_get_contents($base.'/'.$key.'/patch.json'),true,flags:JSON_THROW_ON_ERROR);
  foreach($expectedPatch as $field=>$value)if($landing->getRawOriginal($field)!=$value)throw new RuntimeException('Existing landing differs: '.$key.' '.$field);
  if($location->partner_id!==$partner->id||$location->city_id!==$city->id||$landing->publish_scope!=='NONE')throw new RuntimeException('Existing relation differs');
  foreach($item['product'] as $field=>$value){if(in_array($field,['partner_slug','location_slugs']))continue;if($field==='ends_at'){$value=Carbon\Carbon::parse($value)->toIso8601String();$actual=$existing->$field?->toIso8601String();}else{$actual=$existing->$field;}if($actual!=$value)throw new RuntimeException('Existing product differs: '.$key.' '.$field);}
  if($landing->status!=='DRAFT'||$landing->default_scope!=='NONE'||$partner->status!=='draft'||$location->status!=='draft')throw new RuntimeException('Existing record unsafe');
  $results[]=['key'=>$key,'partner_id'=>$partner->id,'location_id'=>$location->id,'product_id'=>$existing->id,'landing_id'=>$landing->id,'action'=>'verified-existing'];continue;
 }
 if($matches->count())throw new RuntimeException('Partial partner without product requires explicit checkpoint recovery');
 foreach(Location::all() as $location){foreach(['slug','address','maps_url','phone','email','instagram'] as $field){$value=$item['location'][$field]??null;if($value && $normalize($value)===$normalize($location->$field))throw new RuntimeException('Existing location requires mapping: '.$key);}}
 $dir=$base.'/'.$key.'/csv';@mkdir($dir,0755,true);
 $data=['partners.csv'=>[$item['partner']],'locations.csv'=>[$item['location']], 'benefits.csv'=>$item['domain']==='Benefit'?[$item['product']]:[], 'experiences.csv'=>$item['domain']==='Experience'?[$item['product']]:[], 'experience_partners.csv'=>$item['domain']==='Experience'?[['experience_slug'=>$item['product']['slug'],'partner_slug'=>$key,'role'=>'organizer','sort_order'=>0]]:[], 'experience_sessions.csv'=>[]];
 foreach(App\Console\Commands\CatalogV2TemplateCommand::HEADERS as $file=>$headers){$f=fopen($dir.'/'.$file,'w');fputcsv($f,$headers,escape:'');foreach($data[$file] as $row){$values=[];foreach($headers as $h){$v=$row[$h]??null;$values[]=is_bool($v)?($v?'true':'false'):str_replace(["\r","\n"],' ',(string)$v);}fputcsv($f,$values,escape:'');}fclose($f);}
 if(Artisan::call('jakawi:import-catalog-v2',['directory'=>$dir])!==0)throw new RuntimeException(Artisan::output());
 if($item['domain']==='Experience'){Validator::make($item['product'],App\Support\ExperienceRules::experience())->validate();Validator::make($item['product'],App\Support\ExperienceRules::commercial())->validate();}
 $patch=json_decode(file_get_contents($base.'/'.$key.'/patch.json'),true,flags:JSON_THROW_ON_ERROR);
 if(!$apply){$results[]=['key'=>$key,'action'=>'validated-create','plan'=>Artisan::output()];continue;}
 $result=DB::transaction(function()use($item,$class,$dir,$city,$admin,$patch,$key,$base){
  DB::select("select pg_advisory_xact_lock(20261014, 2)");
  if(Partner::whereSlug($key)->exists()||Location::whereSlug($item['location']['slug'])->exists()||$class::whereSlug($item['product']['slug'])->exists())throw new RuntimeException('Concurrent identity creation');
  if(Artisan::call('jakawi:import-catalog-v2',['directory'=>$dir,'--apply'=>true])!==0)throw new RuntimeException(Artisan::output());
  $partner=Partner::whereSlug($key)->sole();$location=Location::whereSlug($item['location']['slug'])->sole();$location->update(['city_id'=>$city->id]);
  $product=$class::whereSlug($item['product']['slug'])->sole();$data=$item['product'];unset($data['partner_slug'],$data['location_slugs']);
  $product->update($data+['created_by_user_id'=>$admin->id]);
  $kind=$item['domain']==='Benefit'?'beneficios':'experiencias';$parameter=$item['domain']==='Benefit'?'benefit':'experience';
  $request=Request::create('/admin/'.$kind.'/'.$product->slug.'/landings','POST',$patch+['slug'=>$product->slug,'default_scope'=>'NONE']);$request->setUserResolver(fn()=>$admin);
  $route=new Illuminate\Routing\Route('POST','/admin/'.$kind.'/{'.$parameter.'}/landings',[]);$route->bind($request);$request->setRouteResolver(fn()=>$route);
  app(App\Http\Controllers\AdminLandingPresentationController::class)->save($request,$product->slug);
  $landing=$product->landingPresentations()->sole();
  if($landing->status!=='DRAFT'||$landing->default_scope!=='NONE'||$product->status!=='draft')throw new RuntimeException('Unsafe resulting state');
  if($product instanceof Experience && ($product->sessions()->exists()||$product->catalog_visibility!=='hidden'||$product->operations_confirmed))throw new RuntimeException('Unsafe experience');
  $brief=app(App\Services\LandingPresentationEditor::class)->context($product,$request)['aiBrief'];$brief['city']=$city->name;
  return ['key'=>$key,'name'=>$partner->name,'domain'=>$item['domain'],'partner_id'=>$partner->id,'location_id'=>$location->id,'product_id'=>$product->id,'landing_id'=>$landing->id,'title'=>$product->title,'price_bob'=>$item['commercial']['price_per_person_bob'],'status'=>'draft','landing_status'=>'DRAFT','preview'=>'https://jakawi.com/admin/'.$kind.'/'.$product->slug.'/landings/'.$landing->slug.'/preview','brief'=>$brief,'action'=>'created'];
 });
 $results[]=$result;
 file_put_contents($base.'/progress.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
 file_put_contents($base.'/'.$key.'/brief.json',json_encode($result['brief'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
echo json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
