<?php
namespace Tests\Feature;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipBankQr;
use App\Services\JakawiBankReconciliation;
use App\Jobs\ReconcileJakawiBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JakawiAutomaticReconciliationTest extends TestCase {
 use RefreshDatabase;
 private User $admin;
 protected function setUp(): void {
  parent::setUp(); Http::preventStrayRequests(); Storage::fake('membership-private'); Storage::fake('minio-media'); Queue::fake();
  $this->admin=User::factory()->create(['is_admin'=>true]);
  app(MembershipBankQr::class)->save(UploadedFile::fake()->image('qr.png',400,400),$this->admin);
 }
 private function purchase(array $data=[]): MembershipBankPayment {
  $p=app(MembershipBankCheckout::class)->start(User::factory()->create(['name'=>'PERSONA FICTICIA']));
  $p->forceFill(['status'=>'PAYMENT_REPORTED','created_at'=>'2026-09-01 00:00:00',...$data])->save(); return $p;
 }
 private function statement(string $description, string $generation='8:18:05 PM'): array {
  $pdf=str_replace('Concepto ficticio',$description,$this->ganaMovilPdf($generation));
  return app(MembershipBankImport::class)->uploadPdf(UploadedFile::fake()->createWithContent('synthetic.pdf',$pdf),$this->admin);
 }
 private function runReconciliation(): void { app(JakawiBankReconciliation::class)->run(); }
 private function receipt(MembershipBankPayment $p, array $overrides=[]): void {
  $data=['reference'=>'990001','amount'=>'100.00','currency'=>'BOB','date'=>'2026-10-01','time'=>'10:00:00',
   'payer_name'=>'PERSONA FICTICIA','recipient'=>app(MembershipBankQr::class)->recipient(),'confidence'=>'high',...$overrides];
  $p->forceFill(['receipt_path'=>'synthetic.png','extraction_status'=>'completed','extraction_json'=>$data,
   'declared_reference'=>$data['reference'],'paid_date'=>$data['date'],'paid_time'=>$data['time'],'payer_name'=>$data['payer_name'],'status'=>'under_review'])->save();
 }
 public function test_exact_purchase_in_bank_activates_once_without_receipt_despite_false_reports(): void {
  $p=$this->purchase(); for($i=0;$i<5;$i++) {$this->purchase();}
  $r=$this->statement('  jaKaWi   Pionero '.$p->reference); Queue::assertPushed(ReconcileJakawiBank::class);
  $this->runReconciliation(); $this->runReconciliation();
  $this->assertSame('approved',$p->fresh()->status); $this->assertDatabaseCount('memberships',1); $this->assertDatabaseCount('commercial_outbox',1);
  $this->assertDatabaseHas('jakawi_bank_reconciliations',['status'=>'auto_reconciled','payment_id'=>$p->id]);
  $this->assertDatabaseHas('audit_logs',['action'=>'jakawi_bank_automatic_decision']);
  $this->assertTrue($this->statement('  jaKaWi   Pionero '.$p->reference)['duplicate']);
  $this->assertSame(0,$this->statement('  jaKaWi   Pionero '.$p->reference,'8:19:05 PM')['imported']);
  $this->runReconciliation(); $this->assertDatabaseCount('memberships',1); $this->assertDatabaseCount('commercial_outbox',1);
  $this->assertDatabaseCount('membership_bank_movements',2); $this->assertDatabaseCount('jakawi_bank_reconciliations',1);
  $this->actingAs($this->admin)->get('/admin/extractos-bancarios')->assertOk()->assertSee($p->reference)->assertSee('Conciliado automáticamente')->assertSee('Historial bancario completo');
 }
 public function test_amount_and_no_receipt_and_false_reports_never_identify_a_buyer(): void {
  for($i=0;$i<6;$i++) {$this->purchase();}
  $this->statement('JAKAWI Pionero'); $this->runReconciliation();
  $this->assertDatabaseHas('jakawi_bank_reconciliations',['status'=>'unidentified']); $this->assertDatabaseCount('memberships',0);
  $audits=DB::table('audit_logs')->where('action','jakawi_bank_automatic_decision')->count();
  $this->runReconciliation(); $this->assertSame($audits,DB::table('audit_logs')->where('action','jakawi_bank_automatic_decision')->count());
  $this->actingAs($this->admin)->get('/admin/extractos-bancarios?estado=review_required')->assertOk()->assertSee('Sin identificar · Requiere revisión');
 }
 public function test_late_receipt_matches_ach_identifier_in_description_and_replays_are_harmless(): void {
  $p=$this->purchase(); $this->statement('JAKAWI Pionero'); $this->runReconciliation(); $this->assertDatabaseCount('memberships',0);
  $this->receipt($p); $this->runReconciliation(); $this->assertSame('approved',$p->fresh()->status);
  (new ReconcileJakawiBank)->handle(); (new ReconcileJakawiBank)->handle();
  $this->assertDatabaseCount('memberships',1); $this->assertDatabaseCount('commercial_outbox',1);
 }
 public function test_distinct_bank_and_receipt_ids_can_match_by_independent_payer_and_exact_timestamp(): void {
  $p=$this->purchase(); $this->receipt($p,['reference'=>'DIFFERENT-ACH-123']);
  $this->statement('JAKAWI Pionero'); $this->runReconciliation();
  $this->assertSame('approved',$p->fresh()->status);
 }
 public function test_ambiguous_buyers_remain_in_review(): void {
  $p=$this->purchase();$q=$this->purchase();$this->receipt($p);$this->receipt($q);
  $this->statement('JAKAWI'); $this->runReconciliation();
  $this->assertDatabaseHas('jakawi_bank_reconciliations',['status'=>'review_required']); $this->assertDatabaseCount('memberships',0);
 }
 public function test_two_credits_cannot_both_claim_one_purchase(): void {
  $p=$this->purchase(); $this->receipt($p); $this->statement('JAKAWI');
  $m=(array)DB::table('membership_bank_movements')->where('credit','100.00')->first(); unset($m['id']);
  $m['fingerprint']=hash('sha256','second');$m['reference']='700003';$m['description'].=' second';
  DB::table('membership_bank_movements')->insert($m); $this->runReconciliation();
  $this->assertDatabaseCount('memberships',0); $this->assertDatabaseCount('jakawi_bank_reconciliations',2);
 }
 public function test_wrong_amount_contradiction_and_reversal_are_blocked(): void {
  $p=$this->purchase(); $this->statement('JAKAWI '.$p->reference); $this->receipt($p,['amount'=>'99.00']);
  $this->runReconciliation(); $this->assertDatabaseCount('memberships',0);
  $m=DB::table('membership_bank_movements')->where('credit','100.00')->first();
  DB::table('membership_bank_movements')->where('id',$m->id)->update(['amount'=>'99.00','credit'=>'99.00']);
  $this->runReconciliation(); $this->assertDatabaseHas('jakawi_bank_reconciliations',['reason'=>'Importe distinto de Bs 100.']);
  DB::table('membership_bank_movements')->where('id',$m->id)->update(['amount'=>'100.00','credit'=>'100.00','description'=>'JAKAWI REVERSO '.$p->reference]);
  $this->runReconciliation(); $this->assertDatabaseCount('memberships',0);
 }
 public function test_used_movement_and_unvalidated_pdf_cannot_activate(): void {
  $p=$this->purchase(); $r=$this->statement('JAKAWI '.$p->reference);
  DB::table('membership_bank_imports')->where('id',$r['id'])->update(['document_metadata'=>'{}']);
  $this->runReconciliation(); $this->assertDatabaseCount('memberships',0);
  $m=DB::table('membership_bank_movements')->where('credit','100.00')->first();
  $p->forceFill(['bank_movement_id'=>$m->id])->save(); $this->runReconciliation();
  $this->assertDatabaseHas('jakawi_bank_reconciliations',['status'=>'reconciled']); $this->assertDatabaseCount('memberships',0);
 }
 public function test_completed_late_extraction_enqueues_reconciliation_and_activates_existing_purchase(): void {
  $p=$this->purchase(); $this->statement('JAKAWI');
  $version=(string)\Illuminate\Support\Str::uuid();
  Storage::disk('minio-media')->put('late.png','synthetic-bytes');
  $p->forceFill(['status'=>'under_review','receipt_path'=>'late.png','receipt_disk'=>'minio-media','receipt_mime'=>'image/png',
   'receipt_sha256'=>hash('sha256','synthetic-bytes'),'extraction_status'=>'pending','extraction_version'=>$version])->save();
  $vision=$this->partialMock(\App\Services\MembershipReceiptVision::class);
  $vision->shouldReceive('ready')->andReturn(true);
  $vision->shouldReceive('extract')->once()->andReturn(['reference'=>'990001','amount'=>'100.00','currency'=>'BOB','date'=>'2026-10-01','time'=>'10:00:00',
   'payer_name'=>'PERSONA FICTICIA','recipient'=>app(MembershipBankQr::class)->recipient(),'origin_bank'=>null,'confidence'=>'high','evidence'=>[]]);
  (new \App\Jobs\ExtractMembershipReceipt($p->id,$version))->handle($vision);
  Queue::assertPushed(ReconcileJakawiBank::class,2);
  $this->runReconciliation(); $this->assertSame('approved',$p->fresh()->status);
 }
 public function test_club_purchase_uses_existing_activation_and_event_once_without_com_commissions(): void {
  $p=$this->purchase(['origin'=>'CLUB','club_reference'=>(string)\Illuminate\Support\Str::uuid(), 'identity_status'=>'pending', 'buyer_name'=>'PERSONA FICTICIA']);
  $this->statement('JAKAWI '.$p->club_reference); $this->runReconciliation(); $this->runReconciliation();
  $this->assertSame('approved',$p->fresh()->status); $this->assertDatabaseCount('memberships',1); $this->assertDatabaseCount('commercial_outbox',1);
  $body=json_decode(DB::table('commercial_outbox')->sole()->raw_body,true);
  $this->assertSame('membership.activated',$body['event_type']); $this->assertSame($p->club_reference,$body['club_reference']);
  $this->assertDatabaseCount('reward_transactions',0);
 }
 public function test_membership_already_active_rolls_back_and_enters_review(): void {
  $p=$this->purchase();
  app(\App\Services\MembershipService::class)->activate($p->user,$this->admin);
  $this->statement('JAKAWI '.$p->reference); $this->runReconciliation();
  $this->assertSame('PAYMENT_REPORTED',$p->fresh()->status); $this->assertDatabaseCount('memberships',1);
  $this->assertDatabaseHas('jakawi_bank_reconciliations',['status'=>'review_required','payment_id'=>$p->id]);
 }
 public function test_non_jakawi_credits_remain_only_in_full_bank_history(): void {
  $this->purchase(); $this->statement('OTRO CONCEPTO'); $this->runReconciliation();
  $this->assertDatabaseCount('membership_bank_movements',2); $this->assertDatabaseCount('jakawi_bank_reconciliations',0); $this->assertDatabaseCount('memberships',0);
 }
 public function test_explicit_sender_bank_contradiction_blocks_receipt(): void {
  $p=$this->purchase(); $this->receipt($p,['origin_bank'=>'BANCO DIFERENTE']); $this->statement('JAKAWI');
  $this->runReconciliation(); $this->assertDatabaseHas('jakawi_bank_reconciliations',['status'=>'review_required']); $this->assertDatabaseCount('memberships',0);
 }
    private function ganaMovilPdf(string $generated='8:18:05 PM',string $corruption=''): string
    {
        $lines=[
            'EXTRACTO DE CUENTA',
            'NRO. DE CUENTA: 1234567890     EXPRESADO EN BOLIVIANOS     Banco Ganadero S.A. - GanaMóvil',
            'PERIODO DEL 01/10/2026 AL 31/10/2026     Fecha y Hora: 31/10/2026 '.$generated,
            'SALDO ANTERIOR: 0.00',
            'FECHA   HORA   OFICINA/CANAL   TRANSACCIÓN   DESCRIPCIÓN   DÉBITO   CRÉDITO   SALDO', '',
            '                                                   TRANSF. QR ACH Nro. 990001',
            '                                                   BANCO DE PRUEBA',
            '01/10/2026   10:00:00   WEB/MOV/KSC   700001          555000                    0.00   +100.00   100.00',
            '                                                   PERSONA FICTICIA',
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
