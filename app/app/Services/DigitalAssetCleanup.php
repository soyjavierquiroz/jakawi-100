<?php
namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\{DB, Log, Storage};

/** Cleanup is limited to immutable module QR and digital-product editorial images. */
class DigitalAssetCleanup
{
    public function allowed(string $disk, string $path): bool
    {
        $uuid = '[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}';
        return $disk === 'digital-private'
            ? preg_match('#^qr/'.$uuid.'\.(png|jpg)$#D', $path) === 1
            : $disk === config('media.disk') && preg_match('#^digital-products/[1-9][0-9]*/(cover|image)/'.$uuid.'\.webp$#D', $path) === 1;
    }

    public function referenced(string $path): bool
    {
        // Conservatively includes order snapshots, gifts, landings, settings and audits.
        foreach (DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public'") as $table) {
            if (! preg_match('/^[a-z_][a-z0-9_]*$/D', $table->tablename)) return true;
            if (DB::selectOne('SELECT EXISTS(SELECT 1 FROM "'.$table->tablename.'" AS r WHERE strpos(to_jsonb(r)::text, ?) > 0) AS found', [$path])->found) return true;
        }
        return false;
    }

    public function clean(string $disk, array $paths, bool $apply = true): array
    {
        $report = ['deleted'=>0, 'referenced'=>0, 'unreferenced'=>0, 'retained_uncertain'=>0, 'absent'=>0, 'objects'=>[]];
        foreach (array_unique(array_filter($paths)) as $path) {
            if (! $this->allowed($disk, $path)) { $report['retained_uncertain']++; $report['objects'][]=['path'=>$path,'result'=>'retained_uncertain']; continue; }
            try {
                $result = DB::transaction(function () use ($disk, $path, $apply) {
                    // Same account lock as checkout and module writes prevents a new
                    // snapshot between this reference check and deletion.
                    AppSetting::where('key', DigitalCollection::KEY)->lockForUpdate()->first();
                    if ($this->referenced($path)) return 'referenced';
                    $store = Storage::disk($disk);
                    if (! $store->exists($path)) return 'absent';
                    if (! $apply) return 'unreferenced';
                    if (! $store->delete($path) || $store->exists($path)) return 'retained_uncertain';
                    return 'deleted';
                });
                $report[$result]++;
                $report['objects'][]=['path'=>$path,'result'=>$result];
            } catch (\Throwable $e) { report($e); $report['retained_uncertain']++; $report['objects'][]=['path'=>$path,'result'=>'retained_uncertain']; }
        }
        Log::info('digital_asset_cleanup', ['disk'=>$disk, 'apply'=>$apply] + $report);
        return $report;
    }

    public function afterCommit(string $disk, array $paths): void
    {
        DB::afterCommit(fn () => $this->clean($disk, $paths));
    }

    public function sweep(bool $apply = false): array
    {
        $result=[];
        foreach (['digital-private'=>['qr'], config('media.disk')=>['digital-products']] as $disk=>$prefixes) {
            $paths=[]; $recent=[];
            foreach ($prefixes as $prefix) foreach (Storage::disk($disk)->allFiles($prefix) as $path) {
                if (! $this->allowed($disk,$path)) { $paths[]=$path; continue; }
                if (Storage::disk($disk)->lastModified($path) < now()->subDay()->timestamp) $paths[]=$path;
                else $recent[]=$path;
            }
            $result[$disk]=$this->clean($disk,$paths,$apply);
            $fresh=$this->clean($disk,$recent,false);
            $result[$disk]['referenced'] += $fresh['referenced'];
            $result[$disk]['retained_uncertain'] += $fresh['retained_uncertain'];
            $result[$disk]['retained_recent'] = $fresh['unreferenced'];
            foreach ($fresh['objects'] as $object) {
                if ($object['result']==='unreferenced') $object['result']='retained_recent';
                $result[$disk]['objects'][]=$object;
            }
        }
        return $result;
    }
}
