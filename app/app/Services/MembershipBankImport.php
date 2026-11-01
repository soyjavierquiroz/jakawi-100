<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipBankImport
{
    /** Mobile upload uses only metadata read by the already verified GanaMóvil parser. */
    public function uploadPdf(UploadedFile $file, User $admin): array
    {
        abort_unless($admin->is_admin, 403);
        $hash = hash_file('sha256', $file->getRealPath());
        $prior = DB::table('membership_bank_imports')->where('sha256', $hash)->first();
        if ($prior) { return ['id'=>$prior->id, 'duplicate'=>true, 'imported'=>0]; }
        abort_unless(Storage::disk('membership-private')->put('statements/'.$hash.'.pdf', file_get_contents($file->getRealPath())), 503, 'No se pudo guardar el PDF privado.');
        try {
            $document = app(BankStatementParser::class)->parseDocument($file);
        } catch (ValidationException $e) {
            // Failed PDFs remain private and inspectable, never eligible for reconciliation.
            $this->preview($file, 'UNKNOWN', $admin, 'Banco Ganadero', now()->toDateString(), now()->toDateString());
            throw $e;
        }
        $m = $document['metadata'];
        if (($m['format'] ?? '') !== 'banco-ganadero-ganamovil-digital-v1') {
            throw ValidationException::withMessages(['statement'=>'Seleccione un PDF digital GanaMóvil de Banco Ganadero.']);
        }
        // Reuse an existing 11A account alias when the document already identifies it.
        $accounts = DB::table('membership_bank_imports')->where('bank', $m['bank'])
            ->where('document_metadata->account', $m['account'])->distinct()->pluck('account_key');
        if ($accounts->count() > 1) {
            throw ValidationException::withMessages(['statement'=>'La cuenta tiene varios identificadores históricos. Revise sus extractos antes de importar.']);
        }
        $account = $accounts->first() ?? $m['account'];
        $id = $this->preview($file, $account, $admin, $m['bank'], $m['period_start'], $m['period_end'], $document);
        return $this->confirm($id, $admin);
    }

    public function preview(UploadedFile $file, string $account, User $admin, string $bank, string $start, string $end, ?array $document = null): string
    {
        abort_unless($admin->is_admin,403);
        $hash=hash_file('sha256',$file->getRealPath());
        $path='statements/'.$hash.'.'.strtolower($file->getClientOriginalExtension());
        abort_unless(Storage::disk('membership-private')->put($path,file_get_contents($file->getRealPath())),503,'No se pudo conservar el original.');
        $incidents=[]; $rows=[]; $metadata=[];
        try {
            $document ??= app(BankStatementParser::class)->parseDocument($file);
            $rows=$document['rows']; $metadata=$document['metadata'];
            if ($metadata && stripos($bank, 'ganadero') === false) { $incidents[]='El banco declarado no coincide con Banco Ganadero en el PDF.'; }
            if (isset($metadata['account']) && ctype_digit($account) && $account !== $metadata['account']) { $incidents[]='La cuenta declarada no coincide con la cuenta del PDF.'; }
            foreach($rows as $row) { if($row['paid_date']<$start || $row['paid_date']>$end) { $incidents[]='Hay movimientos fuera del período declarado.'; break; } }
        } catch(ValidationException $e) { $incidents=array_merge(...array_values($e->errors())); }
        $id=(string)Str::uuid();
        DB::table('bank_statement_previews')->insert(['id'=>$id,'admin_id'=>$admin->id,'sha256'=>$hash,'path'=>$path,'original_name'=>mb_substr(basename($file->getClientOriginalName()),0,255),
            'account_key'=>$account,'bank'=>$bank,'period_start'=>$start,'period_end'=>$end,'document_metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR),'rows'=>json_encode($rows,JSON_THROW_ON_ERROR),'incidents'=>json_encode($incidents,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        return $id;
    }

    public function confirm(string $id, User $admin): array
    {
        abort_unless($admin->is_admin,403);
        return DB::transaction(function() use($id,$admin) {
            $p=DB::table('bank_statement_previews')->where('id',$id)->lockForUpdate()->first();
            abort_unless($p && $p->admin_id===$admin->id,404);
            $rows=json_decode($p->rows,true,512,JSON_THROW_ON_ERROR); $incidents=json_decode($p->incidents,true,512,JSON_THROW_ON_ERROR);
            if(!$rows || $incidents) { throw ValidationException::withMessages(['statement'=>'El extracto tiene incidencias. Corrija el archivo y vuelva a previsualizarlo.']); }
            abort_unless(hash_equals($p->sha256,hash('sha256',Storage::disk('membership-private')->get($p->path))),422,'El original no coincide con el SHA-256.');
            // Serialize overlap detection per bank account, including concurrent confirmations.
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',['bank-statement:'.$p->bank.':'.$p->account_key]);
            $prior=DB::table('membership_bank_imports')->where('sha256',$p->sha256)->first();
            if($prior) { abort_unless($prior->account_key===$p->account_key && ($prior->bank===null || $prior->bank===$p->bank),409,'Archivo ya registrado con otra cuenta/banco.'); return ['imported'=>0,'duplicate'=>true,'id'=>$prior->id]; }
            $importId=DB::table('membership_bank_imports')->insertGetId(['sha256'=>$p->sha256,'account_key'=>$p->account_key,'path'=>$p->path,'admin_id'=>$admin->id,'bank'=>$p->bank,'original_name'=>$p->original_name,
                'period_start'=>$p->period_start,'period_end'=>$p->period_end,'document_metadata'=>$p->document_metadata,'processing_status'=>'processed','extracted_count'=>count($rows),'incidents'=>'[]','created_at'=>now(),'updated_at'=>now()]);
            $count=0; $duplicates=0; $holds=0;
            foreach($rows as $index=>$row) {
                $fingerprint=hash('sha256',json_encode([$p->bank,$p->account_key,$row['identity'],$row['occurrence']],JSON_THROW_ON_ERROR));
                // Weak rows are retained separately, held for review, never silently merged.
                if($row['weak_identity']) { $fingerprint=hash('sha256',$fingerprint.$p->sha256); }
                $existing=DB::table('membership_bank_movements')->where('fingerprint',$fingerprint)->value('id');
                // Interoperability with the original CSV importer, whose identity omitted time/balance.
                if(!$existing && $row['reference'] && $row['occurrence']===1) {
                    $existing=DB::table('membership_bank_movements as m')->join('membership_bank_imports as i','i.id','=','m.import_id')->where('i.processing_status','legacy')
                        ->where('i.account_key',$p->account_key)->where('m.paid_date',$row['paid_date'])->where('m.paid_time',$row['paid_time'])
                        ->where('m.reference',$row['reference'])->where('m.amount',$row['amount'])->value('m.id');
                }
                $uncertain=false;
                if(!$existing && $row['reference']) {
                    $possible=DB::table('membership_bank_movements as m')->join('membership_bank_imports as i','i.id','=','m.import_id')
                        ->where('i.account_key',$p->account_key)->where(function($q) use($p){$q->where('i.bank',$p->bank)->orWhereNull('i.bank');})
                        ->where('m.paid_date',$row['paid_date'])->where('m.reference',$row['reference'])->where('m.amount',$row['amount'])->get(['m.paid_time','m.balance']);
                    foreach($possible as $other) {
                        if(($other->paid_time===null || $row['paid_time']===null || $other->paid_time===$row['paid_time'])
                            && ($other->balance===null || $row['balance']===null || $other->balance===$row['balance'])) {$uncertain=true;break;}
                    }
                }
                $disposition=$existing?'duplicate':($uncertain || $row['weak_identity'] || $row['occurrence']>1?'review_required':'new');
                if($existing) {$movementId=$existing; $duplicates++;}
                else {
                    $hold=$disposition==='review_required'; if($hold){$holds++;}
                    $data=$row; unset($data['identity'],$data['occurrence'],$data['weak_identity']);
                    $movementId=DB::table('membership_bank_movements')->insertGetId([...$data,'account_key'=>$p->account_key,'fingerprint'=>$fingerprint,'import_id'=>$importId,'reconciliation_hold'=>$hold,'created_at'=>now(),'updated_at'=>now()]);
                    $count++;
                }
                DB::table('bank_statement_entries')->insert(['import_id'=>$importId,'movement_id'=>$movementId,'line'=>$index+1,'disposition'=>$disposition,'original_row'=>$row['original_row'],'data'=>json_encode($row,JSON_THROW_ON_ERROR)]);
            }
            DB::table('membership_bank_imports')->where('id',$importId)->update(['duplicate_count'=>$duplicates,'incidents'=>json_encode($holds?["$holds movimientos sin identidad bancaria suficiente o repetidos dentro del archivo: retenidos para revisión."]:[],JSON_THROW_ON_ERROR)]);
            AuditLog::create(['actor_user_id'=>$admin->id,'action'=>'membership_bank_imported','subject_type'=>'membership_bank_imports','subject_id'=>$importId,'metadata'=>['imported'=>$count,'duplicates'=>$duplicates,'held'=>$holds]]);
            \App\Jobs\ReconcileJakawiBank::dispatch();
            return ['imported'=>$count,'duplicate'=>false,'id'=>$importId];
        });
    }

    /** Existing internal callers retain the validated deterministic CSV entry point. */
    public function import(UploadedFile $file, string $account, User $admin): array
    {
        abort_unless($admin->is_admin,403);
        $rows=app(BankStatementParser::class)->parse($file);
        $dates=array_column($rows,'paid_date');
        return $this->confirm($this->preview($file,$account,$admin,'Banco Ganadero',min($dates),max($dates)),$admin);
    }
}
