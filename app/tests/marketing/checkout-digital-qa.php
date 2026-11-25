<?php
// Fictitious banking data, isolated test database only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') throw new RuntimeException('QA isolation required');
use App\Models\{User,Partner,DigitalProduct,DigitalOrder,DigitalPayment,Membership,AppSetting};
use App\Services\{DigitalFiles,DigitalCollection,DigitalProductQr,DigitalGifts,DigitalCheckout,DigitalSaleAvailability};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
function pdfQa($name) { return UploadedFile::fake()->createWithContent($name.'.pdf', "%PDF-1.4\n% FICTICIO QA $name\n%%EOF"); }
$action=$argv[1]??'fixture';
if ($action==='fixture') {
 config(['jakawi.prelaunch_mode'=>false,'digital_gifts.enabled'=>true,'digital_gifts.welcome_prelaunch_enabled'=>true]);
 $admin=User::factory()->create(['name'=>'Admin QA','email'=>'admin@checkout.qa.invalid','is_admin'=>true]);
 foreach (['free','member','gift'] as $kind) {
  $users[$kind]=User::factory()->create(['name'=>'Checkout QA '.$kind,'email'=>$kind.'@checkout.qa.invalid','password'=>Illuminate\Support\Facades\Hash::make('CheckoutQa!123456')]);
  if ($kind!=='free') Membership::create(['user_id'=>$users[$kind]->id,'status'=>'active','type'=>'pioneer','starts_at'=>now()->subDay(),'ends_at'=>now()->addYear(),'amount_paid'=>'100.00']);
 }
 $pdf=app(DigitalFiles::class)->store(pdfQa('producto'),'products');
 $cover=UploadedFile::fake()->image('cover.png',400,300)->getContent();
 Storage::disk('public')->put('checkout-qa-cover.png',$cover);
 $p=DigitalProduct::create(['partner_id'=>Partner::where('slug','jakawi-digital')->value('id'),'slug'=>'gluteo-perfecto','name'=>'Glúteo Perfecto QA','status'=>'published','public_price'=>'49.00','member_price'=>'29.00','currency'=>'BOB','description'=>'Producto ficticio de QA','short_description'=>'Solo para validar checkout','support_information'=>'Soporte QA','access_terms'=>'Solo QA','file_path'=>$pdf['path'],'file_sha256'=>$pdf['sha256'],'gift_pdf_sha256'=>$pdf['sha256'],'welcome_gift_enabled'=>true,'cover_path'=>'checkout-qa-cover.png']);
 AppSetting::updateOrCreate(['key'=>DigitalCollection::KEY],['value'=>['enabled'=>true,'exclusive_account'=>true,'bank'=>'BANCO FICTICIO QA','account'=>'QA-000','holder'=>'NO REAL','currency'=>'BOB','version'=>'checkout-qa-v1','account_key'=>hash('sha256','checkout-qa')]]);
 foreach (['public','member'] as $tier) app(DigitalProductQr::class)->save($p,$tier,UploadedFile::fake()->image('QA-NO-PAGAR-'.$tier.'.png',100,100),true,$admin->id);
 app(DigitalSaleAvailability::class)->configure($p,true,true,$admin->id);
 AppSetting::updateOrCreate(['key'=>DigitalGifts::COURTESY_KEY],['value'=>['enabled'=>true,'product_id'=>$p->id,'pdf_sha256'=>$p->file_sha256]]);
 app(DigitalGifts::class)->backfillWelcome($users['member']);
 app(DigitalGifts::class)->backfillWelcome($users['gift']);
 app(DigitalGifts::class)->claim($users['gift'],$p);
 echo json_encode(['result'=>'fixture ready','users'=>collect($users)->map->only('id','email')]);
} elseif ($action==='landing') {
 $p=DigitalProduct::where('slug','gluteo-perfecto')->sole();
 \App\Models\LandingPresentation::firstOrCreate(['slug'=>'checkout-qa'],['subject_type'=>DigitalProduct::class,'subject_id'=>$p->id,'name'=>'Checkout QA','status'=>'PUBLISHED','headline'=>'Glúteo Perfecto QA','template_key'=>'OFFER_CONVERSION','marketing_theme'=>'LIGHT_CONVERSION','media_mode'=>'NONE','editorial_blocks'=>[['type'=>'hero','enabled'=>true,'title'=>'Glúteo Perfecto QA'],['type'=>'final','enabled'=>true,'title'=>'Tu producto digital']]]);
 echo 'QA landing ready';
} elseif ($action==='approve') {
 $payment=DigitalPayment::where('status','under_review')->latest('id')->firstOrFail();
 $order=$payment->order;
 app(DigitalCheckout::class)->confirm($payment,User::where('email','admin@checkout.qa.invalid')->sole(),['bank_reference'=>'QA-CHECKOUT-'.$payment->id,'bank_date'=>now()->timezone('America/La_Paz')->toDateString(),'received_amount'=>$order->amount,'currency'=>$order->currency,'account_key'=>$order->collection_snapshot['account_key'],'bank_verified'=>true,'statement'=>pdfQa('extracto')]);
 echo 'QA approved';
} else throw new RuntimeException('Unknown QA action');
