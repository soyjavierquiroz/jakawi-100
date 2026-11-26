<?php
namespace App\Services;

use App\Models\DigitalProduct;
use Illuminate\Http\Request;

class DigitalAdminCatalog
{
    public function listing(Request $request, DigitalCatalog $catalog): array
    {
        $filters = $request->validate([
            'q'=>'nullable|string|max:180', 'state'=>'nullable|in:all,draft,published,active,ready,pending,missing',
            'category'=>'nullable|string|max:180', 'type'=>'nullable|string|max:40', 'qr'=>'nullable|in:shared,specific,pending',
            'sort'=>'nullable|in:attention,name_asc,name_desc,created_desc,created_asc,updated_desc,category,price_asc,price_desc,commercial',
            'per_page'=>'nullable|in:25,50,100', 'page'=>'nullable|integer|min:1',
        ]);
        $partner = $catalog->internalPartner();
        $base = DigitalProduct::where('partner_id', $partner->id);
        $snapshot = app(DigitalAdminSnapshot::class);
        $snapshot->start((clone $base)->whereNotNull('app_key')->pluck('app_key')->all());
        $states = []; $counts = ['total'=>0, 'active'=>0, 'ready'=>0, 'missing'=>0];
        // Bounded server-side scan: no galleries, cover objects or catalog records sent to the client.
        (clone $base)->select(['id','partner_id','slug','name','status','app_key','delivery_type','currency','public_price','member_price','short_description','description','category','support_information','access_terms','cover_path','file_path','file_sha256'])->chunkById(200, function ($rows) use (&$states, &$counts, $partner) {
            foreach ($rows as $p) {
                $p->setRelation('partner', $partner);
                $sale = app(DigitalSaleAvailability::class);
                $missing = $sale->missing($p, null, true);
                $active = $sale->canSell($p);
                $qr = app(DigitalProductQr::class);
                $states[$p->id] = ['available'=>(bool)$active, 'publish_missing'=>$missing,
                    'qr'=> $qr->missing($p) ? 'pending' : ($qr->usesShared($p) ? 'shared' : 'specific')];
                $counts['total']++; $counts['active'] += (int)$active;
                $counts['ready'] += (int)(!$missing && !$active); $counts['missing'] += (int)(bool)$missing;
            }
        });
        $query = clone $base;
        if ($q = trim($filters['q'] ?? '')) {
            $query->where(function ($query) use ($q) {
                foreach (['name','slug','category','app_key'] as $field) $query->orWhere($field, 'ilike', '%'.addcslashes($q, '%_\\').'%');
                foreach (config('jakawi.category_labels') as $key=>$label) if (mb_stripos($label, $q) !== false) $query->orWhere('category', $key);
            });
        }
        if ($category = $filters['category'] ?? null) $category === 'none' ? $query->whereNull('category') : $query->where('category',$category);
        if ($type = $filters['type'] ?? null) $query->where('delivery_type',$type);
        $state = $filters['state'] ?? 'all';
        if (in_array($state, ['draft','published'])) $query->where('status',$state);
        if (in_array($state, ['active','ready','pending','missing']) || !empty($filters['qr'])) {
            $ids = array_keys(array_filter($states, fn ($s) => match ($state) {
                'active'=>$s['available'], 'ready'=>!$s['available'] && !$s['publish_missing'],
                'pending'=>!$s['available'], 'missing'=>(bool)$s['publish_missing'], default=>true,
            } && (empty($filters['qr']) || $s['qr'] === $filters['qr'])));
            $query->whereIn('id',$ids);
        }
        $sort = $filters['sort'] ?? 'attention';
        if (in_array($sort, ['attention','commercial'])) {
            $priority = array_keys(array_filter($states, fn ($s) => $sort === 'attention' ? !$s['available'] : $s['available']));
            if ($priority) $query->orderByRaw('CASE WHEN id IN ('.implode(',', array_map('intval',$priority)).') THEN 0 ELSE 1 END');
            $query->orderBy('name');
        } else {
            [$column,$direction] = match ($sort) {
                'name_asc'=>['name','asc'], 'name_desc'=>['name','desc'], 'created_asc'=>['created_at','asc'],
                'created_desc'=>['created_at','desc'], 'updated_desc'=>['updated_at','desc'], 'category'=>['category','asc'],
                'price_desc'=>['public_price','desc'], default=>['public_price','asc'],
            };
            $query->orderBy($column,$direction);
        }
        $products = $query->select(['id','name','slug','category','delivery_type','public_price','member_price','currency','status','cover_path','app_key'])->orderBy('id')->paginate((int)($filters['per_page'] ?? 25))->withQueryString()->through(fn ($p) => $p->only('id','name','slug','category','delivery_type','public_price','member_price','currency','status','app_key') + [
            'thumbnail'=>app(MediaUrl::class)->url($p->cover_path,'thumbnail',96), 'sale'=>$states[$p->id],
        ]);
        $snapshot->active = false;
        return ['products'=>$products, 'filters'=>$filters, 'counts'=>$counts, 'partner'=>$partner->only('id','name'),
            'categoryLabels'=>config('jakawi.category_labels'), 'types'=>(clone $base)->distinct()->pluck('delivery_type')];
    }
}
