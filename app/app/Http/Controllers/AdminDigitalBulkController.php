<?php
namespace App\Http\Controllers;

use App\Models\{AuditLog, AppSetting, DigitalProduct};
use App\Services\{DigitalCatalog, DigitalCollection, DigitalProductQr, DigitalSaleAvailability, HostedApps};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB, Validator};
use Illuminate\Validation\{Rule, ValidationException};

class AdminDigitalBulkController extends Controller
{
    public function __invoke(Request $request, DigitalCatalog $catalog)
    {
        $data = $request->validate([
            'ids'=>'required|array|min:1|max:1000', 'ids.*'=>'required|integer|distinct',
            'changes'=>'required|array:category,public_price,member_price,qr_mode,fill_defaults,operation',
            'changes.category'=>['sometimes','nullable',Rule::in(config('jakawi.digital_categories'))],
            'changes.public_price'=>'sometimes|required|decimal:0,2|gt:0|max:99999999.99',
            'changes.member_price'=>'sometimes|required|decimal:0,2|gt:0|max:99999999.99',
            'changes.qr_mode'=>'sometimes|in:shared,specific', 'changes.fill_defaults'=>'sometimes|accepted',
            'changes.operation'=>'sometimes|in:publish_sale,disable_sale',
            'phase'=>'required|in:preview,apply', 'confirmation'=>'nullable|string',
        ]);
        abort_unless(count($data['changes']),422,'Elige al menos un campo.');
        $ids = $data['ids']; sort($ids);
        $query = DigitalProduct::where('partner_id',$catalog->internalPartner()->id)->whereIn('id',$ids);
        abort_unless((clone $query)->count() === count($ids),403);
        $digest = hash('sha256',json_encode([$request->user()->id,$ids,$data['changes']]));
        if ($data['phase'] === 'apply') {
            try { $confirmation = json_decode(Crypt::decryptString($data['confirmation'] ?? ''),true); }
            catch (\Throwable) { abort(422,'Revisa la vista previa antes de aplicar.'); }
            abort_unless(($confirmation['digest'] ?? '') === $digest && ($confirmation['expires'] ?? 0) >= time(),422,'La vista previa venció o cambió la selección. Revísala nuevamente.');
        }
        $results = []; $applied = 0;
        foreach ($ids as $id) {
            try {
                $result = DB::transaction(function () use ($id,$data,$request) {
                    $p = DigitalProduct::with('partner')->whereKey($id)->lockForUpdate()->firstOrFail();
                    AppSetting::where('key',DigitalCollection::KEY)->lockForUpdate()->first();
                    $before = $p->only('category','public_price','member_price');
                    $changes = $data['changes'];
                    $p->fill(array_intersect_key($changes,array_flip(['category','public_price','member_price'])));
                    Validator::make($p->only('public_price','member_price'), ['public_price'=>'nullable|numeric|gt:0','member_price'=>'nullable|numeric|gt:0|lte:public_price'])->validate();
                    if (!empty($changes['fill_defaults']) && $p->delivery_type === 'hosted_app') {
                        foreach (['support_information','access_terms'] as $field) if (blank($p->$field)) $p->$field = config('jakawi-digital.plr_defaults.'.$field);
                    }
                    $qr = app(DigitalProductQr::class); $sale = app(DigitalSaleAvailability::class);
                    $missing = [];
                    if (($changes['operation'] ?? '') === 'disable_sale' && !$sale->eligible($p)) $missing[] = 'Producto no compatible con autorización comercial.';
                    if (($changes['qr_mode'] ?? '') === 'shared') $missing = $qr->sharedMissing($p);
                    // Preview QR mode through request-local inputs; never writes or deletes QR files.
                    $snapshot = app(\App\Services\DigitalAdminSnapshot::class);
                    if ($data['phase'] === 'preview' && isset($changes['qr_mode'])) {
                        $snapshot->start([$p->app_key]);
                        $settings = $snapshot->settings[$qr->key($p)] ?? [];
                        $settings['use_shared'] = $changes['qr_mode'] === 'shared';
                        $snapshot->settings[$qr->key($p)] = $settings;
                    }
                    if (($changes['operation'] ?? '') === 'publish_sale') {
                        // Fresh APP protection checks, same publication validation used individually.
                        $freshReady = $p->delivery_type === 'hosted_app' ? app(HostedApps::class)->ready($p,true) : false;
                        if ($data['phase'] === 'preview') {
                            $snapshot->health['hosted-app-health:'.$p->app_key] = $freshReady;
                            $missing = array_merge($missing,$sale->missing($p,null,true));
                        }
                    }
                    $snapshot->active = false;
                    if ($data['phase'] === 'apply' && !$missing) {
                        $p->save();
                        if (isset($changes['qr_mode'])) $qr->setShared($p,$changes['qr_mode'] === 'shared');
                        if (($changes['operation'] ?? '') === 'publish_sale') $missing = $sale->publishAndSell($p,$request->user()->id);
                        if (($changes['operation'] ?? '') === 'disable_sale') $sale->configure($p,false,false,$request->user()->id);
                        if ($missing) throw ValidationException::withMessages(['product'=>$missing]);
                        AuditLog::create(['actor_user_id'=>$request->user()->id,'action'=>'digital_product_bulk_updated','subject_type'=>DigitalProduct::class,'subject_id'=>$p->id,'metadata'=>['fields'=>array_keys($changes),'before'=>$before,'after'=>$p->only('category','public_price','member_price')]]);
                    }
                    return ['id'=>$p->id,'name'=>$p->name,'missing'=>array_values(array_unique($missing)), 'before'=>$before,'after'=>$p->only('category','public_price','member_price')];
                });
            } catch (ValidationException $e) {
                app(\App\Services\DigitalAdminSnapshot::class)->active = false;
                $result = ['id'=>$id,'name'=>DigitalProduct::findOrFail($id)->name,'missing'=>array_merge(...array_values($e->errors()))];
            }
            $results[] = $result;
            if (!$result['missing']) $applied++;
        }
        return response()->json(['results'=>$results,'ready'=>$applied,'blocked'=>count($ids)-$applied,
            'confirmation'=>$data['phase'] === 'preview' ? Crypt::encryptString(json_encode(['digest'=>$digest,'expires'=>time()+900])) : null,
            'message'=>$data['phase'] === 'apply' ? $applied.(($data['changes']['operation'] ?? '') === 'publish_sale' ? ' publicados' : ' actualizados').'; '.(count($ids)-$applied).' omitidos por requisitos pendientes.' : null]);
    }
}
