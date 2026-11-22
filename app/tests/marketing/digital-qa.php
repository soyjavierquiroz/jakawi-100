<?php
// Isolated QA only. Never load production environment or use real banking data.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('testing') || config('database.connections.pgsql.database')!=='jakawi_test' || config('database.connections.pgsql.host')!=='db-test')throw new RuntimeException('QA isolation required');
config(['jakawi.prelaunch_mode'=>false,'jakawi.analytics.enabled'=>true,'crm.enabled'=>false,'commercial.enabled'=>false,'meta.enabled'=>false,'mail.default'=>'array']);
use App\Models\{User,Partner,DigitalProduct,DigitalOrder,DigitalPayment,DigitalAccessGrant,AppSetting,Membership};
use App\Services\{DigitalFiles,DigitalCollection,DigitalCheckout};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage,DB};
function pdf($text){return UploadedFile::fake()->createWithContent('qa.pdf',"%PDF-1.4\n1 0 obj <</Type /Catalog>> endobj\n% $text\n%%EOF");}
$action=$argv[1]??'fixture';
if($action==='fixture'){
 $partner=Partner::firstOrCreate(['slug'=>'jakawi-digital'],['name'=>'JAKAWI Digital','status'=>'published','entity_type'=>'organization','partner_type'=>'brand']);
 $users=[];
 foreach(['buyer','admin','other','member'] as $role){$users[$role]=User::factory()->create(['name'=>'QA Digital '.ucfirst($role),'email'=>'digital-'.$role.'@qa.invalid','password'=>Illuminate\Support\Facades\Hash::make('DigitalQa!123456'),'is_admin'=>$role==='admin','email_verified_at'=>now()]);}
 Membership::create(['user_id'=>$users['member']->id,'status'=>'active','type'=>'standard','starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'amount_paid'=>'100.00']);
 $file=app(DigitalFiles::class)->store(pdf('PDF FICTICIO QA DIGITAL'),'products');
 for($i=1;$i<=3;$i++)DigitalProduct::create(['partner_id'=>$partner->id,'name'=>'Guía digital QA '.$i,'slug'=>'guia-digital-qa-'.$i,'description'=>'Material ficticio exclusivo para pruebas. Contenido buscable: creatividad y organización.','short_description'=>'PDF de prueba para verificar compra y biblioteca.','commercial_content'=>'Contenido de demostración QA. Sin uso comercial.','support_information'=>'Soporte QA: digital@qa.invalid','access_terms'=>'Uso exclusivo en entorno QA; datos y banco ficticios.','status'=>'published','public_price'=>'20.00','member_price'=>'12.50','currency'=>'BOB','file_path'=>$file['path'],'file_sha256'=>$file['sha256']]);
 $im=imagecreatetruecolor(400,400);$white=imagecolorallocate($im,255,255,255);$black=imagecolorallocate($im,0,0,0);imagefill($im,0,0,$white);imagestring($im,5,30,170,'QA - NO ES UN QR DE PAGO',$black);ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);
 Storage::disk('digital-private')->put('qr/qa.png',$png);
 AppSetting::updateOrCreate(['key'=>DigitalCollection::KEY],['value'=>['enabled'=>true,'exclusive_account'=>true,'variable_amount_qr'=>true,'bank'=>'BANCO FICTICIO QA','account'=>'QA-EXCLUSIVA-000','holder'=>'JAKAWI DIGITAL QA - NO REAL','currency'=>'BOB','version'=>'qa-only-v1','account_key'=>hash('sha256','qa-bank'),'qr_path'=>'qr/qa.png','qr_sha256'=>hash('sha256',$png)]]);
 AppSetting::updateOrCreate(['key'=>'show_demo_banner'],['value'=>['enabled'=>false]]);
 echo json_encode(['users'=>collect($users)->map->only('id','email'),'product'=>'guia-digital-qa-1','account_key'=>hash('sha256','qa-bank')]);
}elseif($action==='race-fixture'){
 $product=DigitalProduct::where('slug','guia-digital-qa-2')->firstOrFail();$checkout=app(DigitalCheckout::class);$ids=[];
 for($i=0;$i<3;$i++){$user=User::factory()->create();$order=$checkout->start($user,$product);$payment=$checkout->report($order,$user,pdf('race-receipt-'.$i));$ids[]=$payment->id;}
 echo json_encode(['payments'=>$ids,'admin'=>User::where('email','digital-admin@qa.invalid')->value('id')]);
}elseif($action==='approve'){
 $payment=DigitalPayment::findOrFail((int)$argv[2]);$admin=User::where('email','digital-admin@qa.invalid')->firstOrFail();$order=$payment->order;
 $e=['bank_reference'=>$argv[3],'bank_date'=>now()->timezone('America/La_Paz')->toDateString(),'received_amount'=>$order->amount,'currency'=>$order->currency,'account_key'=>$order->collection_snapshot['account_key'],'bank_verified'=>true,'statement'=>pdf('race statement')];
 try{DB::beginTransaction();DigitalOrder::lockForUpdate()->findOrFail($order->id);usleep(600000);app(DigitalCheckout::class)->confirm($payment,$admin,$e);DB::commit();echo 'confirmed';}
 catch(Throwable $e){if(DB::transactionLevel())DB::rollBack();if($e instanceof Illuminate\Validation\ValidationException || ($e instanceof Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode()===422)){echo 'duplicate';}else throw $e;}
}elseif($action==='state'){
 echo json_encode(['orders'=>DigitalOrder::select('id','reference','user_id','status')->get(),'payments'=>DigitalPayment::select('id','digital_order_id','status','bank_reference')->get(),'grants'=>DigitalAccessGrant::select('id','digital_order_id','status','delivery_status')->get()]);
}else throw new RuntimeException('Unknown QA action');
