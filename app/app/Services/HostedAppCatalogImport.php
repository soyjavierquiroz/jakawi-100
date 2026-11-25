<?php
namespace App\Services;

use App\Models\{AppSetting, DigitalProduct};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HostedAppCatalogImport
{
    public function run(array $data): array
    {
        Validator::make($data, [
            'slug' => ['required','max:180','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D','not_in:gluteos,gluteo-perfecto'],
            'name' => 'required|string|max:255', 'language' => ['required','regex:/^es(?:[-_][A-Za-z0-9]+)*$/D'],
            'vendor_id' => 'required|string|max:180|not_in:gluteo_perfeito_es', 'package_sha256' => 'required|regex:/^[a-f0-9]{64}$/D',
            'description' => 'nullable|string|max:30000', 'category' => ['nullable',Rule::in(config('jakawi.categories'))],
            'cover_base64' => 'nullable|string|max:14000000',
        ])->validate();
        AppSetting::firstOrCreate(['key'=>'hosted_app_catalog'], ['value'=>['slugs'=>[]]]);
        return DB::transaction(function () use ($data) {
            $registry = AppSetting::where('key','hosted_app_catalog')->lockForUpdate()->firstOrFail();
            $key = 'hosted_app_import:'.$data['slug'];
            $prior = AppSetting::where('key',$key)->first();
            if ($prior) {
                if (($prior->value['package_sha256'] ?? '') !== $data['package_sha256']) throw new \RuntimeException('Paquete distinto: instalación existente. Resolver explícitamente antes de reemplazar.');
                $product = DigitalProduct::findOrFail($prior->value['product_id']);
                if ($product->app_key !== $data['slug'] || $product->delivery_type !== 'hosted_app') throw new \RuntimeException('Producto existente desvinculado; requiere revisión.');
                return ['product_id'=>$product->id,'status'=>$product->status,'result'=>'unchanged'];
            }
            if (DigitalProduct::where('slug',$data['slug'])->orWhere('app_key',$data['slug'])->exists()
                || AppSetting::where('key','like','hosted_app_import:%')->get()->contains(fn ($s) => ($s->value['vendor_id'] ?? null) === $data['vendor_id'])) {
                throw new \RuntimeException('Slug o identificador EvaFunnels existente; no se duplicará.');
            }
            $product = DigitalProduct::create([
                'partner_id'=>app(DigitalCatalog::class)->internalPartner()->id, 'slug'=>$data['slug'], 'name'=>$data['name'],
                'description'=>$data['description'] ?? null, 'category'=>$data['category'] ?? null,
                'delivery_type'=>'hosted_app', 'app_key'=>$data['slug'], 'status'=>'draft',
                'public_price'=>null, 'member_price'=>null, 'welcome_gift_enabled'=>false, 'promotional_gift_enabled'=>false,
            ]);
            if (!empty($data['cover_base64'])) {
                if (config('media.disk') !== 'minio-media') throw new \RuntimeException('Se requiere MinIO para importar portadas.');
                $bytes = base64_decode($data['cover_base64'], true);
                if ($bytes === false) throw new \RuntimeException('Portada base64 inválida.');
                $tmp = tempnam(sys_get_temp_dir(),'plr-cover-');
                try {
                    file_put_contents($tmp,$bytes);
                    $product->cover_path = app(MediaUploadService::class)->store(new UploadedFile($tmp,'cover.png',null,null,true),'digital-products',$product->id,'cover');
                    $product->save();
                } finally { @unlink($tmp); }
            }
            AppSetting::create(['key'=>$key,'value'=>['product_id'=>$product->id,'vendor_id'=>$data['vendor_id'],'package_sha256'=>$data['package_sha256'],'language'=>$data['language']]]);
            $registry->value = ['slugs'=>array_values(array_unique([...($registry->value['slugs'] ?? []),$data['slug']]))];
            $registry->save();
            return ['product_id'=>$product->id,'status'=>'draft','result'=>'created'];
        });
    }
}
