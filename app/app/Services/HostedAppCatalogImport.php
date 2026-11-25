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
            'fill_missing' => 'sometimes|boolean', 'short_description' => 'nullable|string|max:1000', 'description' => 'nullable|string|max:30000', 'category' => ['nullable',Rule::in(config('jakawi.categories'))],
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
                $filled = !empty($data['fill_missing']) ? $this->fillMissing($product, $data) : [];
                return ['product_id'=>$product->id,'status'=>$product->status,'result'=>$filled ? 'filled_missing' : 'unchanged','filled'=>$filled];
            }
            if (DigitalProduct::where('slug',$data['slug'])->orWhere('app_key',$data['slug'])->exists()
                || AppSetting::where('key','like','hosted_app_import:%')->get()->contains(fn ($s) => ($s->value['vendor_id'] ?? null) === $data['vendor_id'])) {
                throw new \RuntimeException('Slug o identificador EvaFunnels existente; no se duplicará.');
            }
            $product = DigitalProduct::create([
                'partner_id'=>app(DigitalCatalog::class)->internalPartner()->id, 'slug'=>$data['slug'], 'name'=>$data['name'],
                ...$this->defaults($data),
                'delivery_type'=>'hosted_app', 'app_key'=>$data['slug'], 'status'=>'draft',
                'welcome_gift_enabled'=>false, 'promotional_gift_enabled'=>false,
            ]);
            $this->storeCover($product, $data);
            AppSetting::create(['key'=>$key,'value'=>['product_id'=>$product->id,'vendor_id'=>$data['vendor_id'],'package_sha256'=>$data['package_sha256'],'language'=>$data['language']]]);
            $registry->value = ['slugs'=>array_values(array_unique([...($registry->value['slugs'] ?? []),$data['slug']]))];
            $registry->save();
            return ['product_id'=>$product->id,'status'=>'draft','result'=>'created'];
        });
    }
    private function defaults(array $data): array
    {
        return [
            'short_description'=>$data['short_description'] ?? (filled($data['description'] ?? null) ? \Illuminate\Support\Str::limit(strip_tags($data['description']), 997) : null),
            'description'=>$data['description'] ?? null, 'category'=>$data['category'] ?? null,
            'currency'=>'BOB', 'public_price'=>'49.00', 'member_price'=>'29.00',
        ];
    }

    private function fillMissing(DigitalProduct $product, array $data): array
    {
        if ($product->id === 1 || $product->app_key === 'gluteos') throw new \RuntimeException('Glúteos está excluido del completado automático.');
        $filled=[];
        foreach ($this->defaults($data) as $field=>$value) {
            if (!blank($product->$field) || blank($value)) continue;
            // Never violate an owner-edited price to install the other default.
            if ($field === 'public_price' && filled($product->member_price) && (float)$product->member_price > (float)$value) continue;
            if ($field === 'member_price' && filled($product->public_price) && (float)$value > (float)$product->public_price) continue;
            $product->$field=$value;
            $filled[]=$field;
        }
        if ($product->isDirty()) $product->save();
        if (blank($product->cover_path) && !empty($data['cover_base64'])) {
            $this->storeCover($product,$data);
            $filled[]='cover_path';
        }
        return $filled;
    }

    private function storeCover(DigitalProduct $product, array $data): void
    {
        if (empty($data['cover_base64'])) return;
        if (config('media.disk') !== 'minio-media') throw new \RuntimeException('Se requiere MinIO para importar portadas.');
        $bytes=base64_decode($data['cover_base64'],true);
        if ($bytes===false) throw new \RuntimeException('Portada base64 inválida.');
        $tmp=tempnam(sys_get_temp_dir(),'plr-cover-');
        try {
            file_put_contents($tmp,$bytes);
            $product->cover_path=app(MediaUploadService::class)->store(new UploadedFile($tmp,'cover.png',null,null,true),'digital-products',$product->id,'cover');
            $product->save();
        } finally { @unlink($tmp); }
    }

}
