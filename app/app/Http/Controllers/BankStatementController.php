<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\MembershipBankPayment;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BankStatementController extends Controller
{
    public function index()
    {
        $imports=DB::table('membership_bank_imports')->leftJoin('users','users.id','=','membership_bank_imports.admin_id')->select('membership_bank_imports.*','users.name as administrator')->latest('membership_bank_imports.id')->paginate(25);
        $previews=DB::table('bank_statement_previews')->select('id','original_name','created_at','incidents')->where('admin_id',request()->user()->id)->latest()->limit(20)->get();
        return response()->view('membership-bank.statements',compact('imports','previews'))->header('Cache-Control','private, no-store');
    }
    public function upload(Request $request, MembershipBankImport $service)
    {
        $request->validate(['statement'=>['required','file','mimes:pdf','mimetypes:application/pdf','extensions:pdf','max:10240']]);
        $result = $service->uploadPdf($request->file('statement'), $request->user());
        return redirect('/admin/extractos-bancarios/'.$result['id'])->with('status',
            $result['duplicate'] ? 'Este PDF ya fue importado. No se duplicaron movimientos.' : 'PDF procesado correctamente. '.$result['imported'].' movimientos nuevos.');
    }

    public function preview(Request $r, MembershipBankImport $service)
    {
        $d=$r->validate(['statement'=>['required','file','mimes:csv,txt,xlsx,pdf','extensions:csv,txt,xlsx,pdf','max:10240'],
            'account_key'=>['required','regex:/\A[A-Z0-9_-]{1,100}\z/'],'bank'=>['required','in:Banco Ganadero'],
            'period_start'=>['required','date_format:Y-m-d'],'period_end'=>['required','date_format:Y-m-d','after_or_equal:period_start']]);
        $id=$service->preview($r->file('statement'),$d['account_key'],$r->user(),$d['bank'],$d['period_start'],$d['period_end']);
        return redirect('/admin/extractos-bancarios/preview/'.$id);
    }
    public function showPreview(Request $r,string $preview)
    {
        $p=DB::table('bank_statement_previews')->where('id',$preview)->where('admin_id',$r->user()->id)->first(); abort_unless($p,404);
        $rows=json_decode($p->rows,true); $incidents=json_decode($p->incidents,true);
        $existing=DB::table('membership_bank_imports')->where('sha256',$p->sha256)->value('id');
        return response()->view('membership-bank.statement-preview',compact('p','rows','incidents','existing'))->header('Cache-Control','private, no-store');
    }
    public function confirm(Request $r,string $preview,MembershipBankImport $service)
    {
        $r->validate(['validated'=>['accepted']]); $result=$service->confirm($preview,$r->user());
        return redirect('/admin/extractos-bancarios/'.$result['id'])->with('status',$result['duplicate']?'Archivo idéntico ya registrado.':'Extracto incorporado; no se activaron membresías.');
    }
    public function show(int $id, MembershipBankCheckout $checkout)
    {
        $import=DB::table('membership_bank_imports')->where('id',$id)->first(); abort_unless($import,404);
        $administrator=User::find($import->admin_id)?->name;
        $entries=DB::table('bank_statement_entries as e')->leftJoin('membership_bank_payments as p','p.bank_movement_id','=','e.movement_id')->where('e.import_id',$id)->select('e.*','p.reference as payment_reference')->orderBy('e.line')->paginate(100);
        $linked=DB::table('bank_statement_entries as e')->join('membership_bank_payments as p','p.bank_movement_id','=','e.movement_id')->where('e.import_id',$id)->distinct()->count('e.movement_id');
        $total=DB::table('bank_statement_entries')->where('import_id',$id)->distinct()->count('movement_id');
        $legacy=DB::table('membership_bank_movements as m')->leftJoin('membership_bank_payments as p','p.bank_movement_id','=','m.id')->where('m.import_id',$id)->select('m.*','p.reference as payment_reference')->orderBy('m.id')->paginate(100,['*'],'legacy_page');
        if($import->processing_status==='legacy') {
            $total=DB::table('membership_bank_movements')->where('import_id',$id)->count();
            $linked=DB::table('membership_bank_movements as m')->join('membership_bank_payments as p','p.bank_movement_id','=','m.id')->where('m.import_id',$id)->count();
        }
        $summary = DB::table('bank_statement_entries')->where('import_id', $id)->selectRaw("COUNT(*) as movements,
            COUNT(*) FILTER (WHERE (data->>'credit')::numeric > 0) as credits,
            COUNT(*) FILTER (WHERE (data->>'debit')::numeric > 0) as debits,
            COALESCE(SUM((data->>'credit')::numeric),0) as credit_total,
            COALESCE(SUM((data->>'debit')::numeric),0) as debit_total,
            COUNT(*) FILTER (WHERE disposition <> 'duplicate') as new_count")->first();
        $overlaps = DB::table('membership_bank_imports')->where('id','<>',$id)->where('bank',$import->bank)
            ->where('account_key',$import->account_key)->where('period_start','<=',$import->period_end)
            ->where('period_end','>=',$import->period_start)->count();
        $movementIds = DB::table('bank_statement_entries')->where('import_id',$id)->pluck('movement_id')->flip();
        $pending = MembershipBankPayment::with('user:id,name')->whereIn('status',['under_review','PAYMENT_REPORTED'])->orderBy('id')->paginate(40, ['*'], 'purchases_page');
        $candidates = [];
        foreach ($pending as $payment) {
            $match = $checkout->match($payment);
            foreach ($match['candidates'] as $movement) {
                if ($movementIds->has($movement->id)) { $candidates[] = ['payment'=>$payment, 'movement'=>$movement]; }
            }
        }
        return response()->view('membership-bank.statement',compact('import','administrator','entries','linked','total','legacy','summary','overlaps','candidates','pending'))->header('Cache-Control','private, no-store');
    }
    public function download(int $id)
    {
        $p=DB::table('membership_bank_imports')->where('id',$id)->first(); abort_unless($p,404);
        return Storage::disk('membership-private')->download($p->path,$p->original_name??'extracto.csv',['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
