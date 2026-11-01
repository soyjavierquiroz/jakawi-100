<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\MembershipBankPayment;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipBankQr;
use App\Services\JakawiBankReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentsAdminPanelTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests(); Queue::fake();
        Storage::fake('membership-private'); Storage::fake('minio-media');
        $this->admin = User::factory()->create(['is_admin'=>true]);
        app(MembershipBankQr::class)->save(UploadedFile::fake()->image('qr.png',400,400),$this->admin);
    }
    private function purchase(string $name, string $status): MembershipBankPayment
    {
        $p = app(MembershipBankCheckout::class)->start(User::factory()->create(['name'=>$name]));
        $p->forceFill(['status'=>$status])->save();
        return $p;
    }
    public function test_list_filters_keep_details_and_exceptional_actions_off_the_main_screen(): void
    {
        $confirmed = $this->purchase('Confirmada ficticia','approved');
        $pending = $this->purchase('Pendiente ficticia','PAYMENT_REPORTED');
        $review = $this->purchase('Revision ficticia','under_review');
        $review->forceFill(['receipt_path'=>'private.png','extraction_status'=>'completed','extraction_json'=>['reference'=>'PRIVATE-EVIDENCE']])->save();
        $before = DB::table('membership_bank_payments')->orderBy('id')->get()->toJson();
        $this->actingAs($this->admin)->get('/admin/conciliacion-membresias')->assertOk()->assertSee('Pagos y conciliación')->assertSee('Importar extracto bancario')->assertDontSee('PRIVATE-EVIDENCE')->assertDontSee('Aprobar seleccionadas')->assertDontSee('Configuración del QR bancario');
        $this->get('/admin/conciliacion-membresias?estado=confirmados')->assertOk()->assertSee('Confirmada ficticia')->assertDontSee('Pendiente ficticia')->assertDontSee('Revision ficticia');
        $this->get('/admin/conciliacion-membresias?estado=pendientes')->assertOk()->assertSee('Pendiente ficticia')->assertDontSee('Confirmada ficticia');
        $this->get('/admin/conciliacion-membresias?estado=revision')->assertOk()->assertSee('Revision ficticia')->assertDontSee('Pendiente ficticia');
        $this->get('/admin/conciliacion-membresias?pago='.$review->id)->assertOk()->assertSee('PRIVATE-EVIDENCE')->assertSee('Revisión excepcional')->assertSee('Movimiento bancario');
        $this->assertSame($before,DB::table('membership_bank_payments')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('memberships',0); $this->assertDatabaseCount('commercial_outbox',0);
    }
    public function test_unidentified_income_has_a_detail_with_original_reason_and_evidence(): void
    {
        $pdf = str_replace('Concepto ficticio','JAKAWI Pionero',$this->ganaMovilPdf());
        app(MembershipBankImport::class)->uploadPdf(UploadedFile::fake()->createWithContent('synthetic.pdf',$pdf),$this->admin);
        app(JakawiBankReconciliation::class)->run();
        $decision = DB::table('jakawi_bank_reconciliations')->where('status','unidentified')->first();
        $this->assertNotNull($decision);
        $this->actingAs($this->admin)->get('/admin/conciliacion-membresias?estado=sin-identificar')->assertOk()->assertSee('Comprador sin identificar')->assertSee('ingreso='.$decision->movement_id,false)->assertDontSee($decision->reason);
        $this->get('/admin/conciliacion-membresias?ingreso='.$decision->movement_id)->assertOk()->assertSee($decision->reason)->assertSee('Ver extracto bancario');
        $this->assertDatabaseCount('memberships',0);
    }
    public function test_settings_and_details_retain_admin_access_controls(): void
    {
        $p = $this->purchase('Persona ficticia','awaiting_payment');
        $this->get('/admin/conciliacion-membresias?pago='.$p->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/conciliacion-membresias?vista=ajustes')->assertForbidden();
        $this->get('/admin/conciliacion-membresias?pago='.$p->id)->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/conciliacion-membresias?vista=ajustes')->assertOk()->assertSee('Configuración del QR bancario');
        $this->get('/admin/conciliacion-membresias?pago=999999')->assertNotFound();
    }
    private function ganaMovilPdf(string $generated='8:18:05 PM',string $corruption='', bool $account=false): string
    {
        $lines=[
            'EXTRACTO DE CUENTA',
            'NRO. DE CUENTA: 1234567890     EXPRESADO EN BOLIVIANOS     Banco Ganadero S.A. - GanaMóvil',
            'PERIODO DEL 01/10/2026 AL 31/10/2026     Fecha y Hora: 31/10/2026 '.$generated,
            'SALDO ANTERIOR: 0.00',
            'FECHA   HORA   OFICINA/CANAL   TRANSACCIÓN   DESCRIPCIÓN   DÉBITO   CRÉDITO   SALDO', '',
            '                                                   TRANSF. QR ACH Nro. 990001',
            '                                                   '.($account?'BANCO NACIONAL DE BOLIVIA S.A.':'BANCO DE PRUEBA'),
            '01/10/2026   10:00:00   WEB/MOV/KSC   700001          '.($account?'4561234987':'555000').'                    0.00   +100.00   100.00',
            '                                                   '.($account?'PERSONA PAGADORA':'PERSONA FICTICIA'),
            '                                                   Concepto ficticio', '',
            '                                                   Transferencia a 1111111111.',
            '01/10/2026   10:01:00   WEB/MOV/KSC   700002          PERSONA FICTICIA          -25.00   0.00   '.($corruption==='balance'?'76.00':'75.00'),
            '                                                   Sin referencia', '',
            'SALDO ACTUAL : 75.00', 'CANTIDAD DÉBITOS: '.($corruption==='count'?'2':'1'), 'CANTIDAD CRÉDITOS: 1', '',
            'Página 1 de '.($corruption==='page'?'2':'1').'     La información del extracto está sujeta a verificación del Banco Ganadero.',
        ];
        if ($corruption==='truncated') {array_splice($lines,6,6);}
        if ($corruption==='unknown') {array_splice($lines,12,0,['FILA DESCONOCIDA']);}
        $stream='BT /F1 8 Tf 20 800 Td ';
        foreach($lines as $i=>$line) {
            $line=iconv('UTF-8','Windows-1252',$line);
            $line=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);
            $stream.=($i?'0 -14 Td ':'').'('.$line.') Tj ';
        }
        $stream.='ET';
        $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 1400 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>','<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream"];
        $pdf="%PDF-1.4\n"; $offsets=[0];
        foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n$object\nendobj\n";}
        $xref=strlen($pdf); $pdf.="xref\n0 6\n0000000000 65535 f \n";
        foreach(array_slice($offsets,1) as $offset){$pdf.=sprintf("%010d 00000 n \n",$offset);}
        return $pdf."trailer << /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

}
