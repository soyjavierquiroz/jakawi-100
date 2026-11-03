<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'db-test' || config('database.connections.pgsql.database') !== 'jakawi_test') throw new RuntimeException('Isolated tests only');
App\Models\City::where('slug', 'cochabamba')->update(['status' => App\Models\City::ACTIVE]);
$admin = App\Models\User::factory()->create(['name'=>'Administrador QA', 'email'=>'support-admin@example.test', 'password'=>'Support-test-only-2026!', 'is_admin'=>true, 'is_superadmin'=>true]);
$free = App\Models\User::factory()->create(['name'=>'Usuario Gratuito QA', 'email'=>'support-free@example.test']);
$pioneer = App\Models\User::factory()->create(['name'=>'Pionero QA', 'email'=>'support-pioneer@example.test']);
App\Models\Membership::create(['user_id'=>$pioneer->id, 'type'=>'pioneer', 'status'=>'active', 'starts_at'=>now()->subDay(), 'ends_at'=>now()->addYear(), 'amount_paid'=>100]);
$owner = App\Models\User::factory()->create(['name'=>'Representante QA', 'email'=>'support-owner@example.test']);
$partner = App\Models\Partner::factory()->published()->create(['name'=>'Partner QA', 'slug'=>'partner-qa']);
$partner->users()->attach($owner, ['role'=>'owner', 'economic_access'=>false]);
$other = App\Models\Partner::factory()->published()->create(['name'=>'Otro Partner QA', 'slug'=>'otro-partner-qa']);
echo json_encode(['admin'=>$admin->id, 'free'=>$free->id, 'pioneer'=>$pioneer->id, 'owner'=>$owner->id, 'partner'=>$partner->id, 'other_partner'=>$other->slug]);
