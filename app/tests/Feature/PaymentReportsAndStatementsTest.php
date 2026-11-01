<?php
namespace Tests\Feature;
use App\Jobs\ExtractMembershipReceipt;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\BankStatementParser;
use App\Services\MembershipBankCheckout;
use App\Services\MembershipBankImport;
use App\Services\MembershipBankQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentReportsAndStatementsTest extends TestCase
{
    use RefreshDatabase;
    private function admin(): User {return User::factory()->create(['is_admin'=>true]);}
    private function file(string $rows): UploadedFile { return UploadedFile::fake()->createWithContent('extracto.csv',"Fecha,Hora,Referencia,Descripción,Débito,Crédito,Saldo\n".$rows); }
    private function preview(UploadedFile $file, User $admin): string {
        return app(MembershipBankImport::class)->preview($file,'GANADERO-BOB',$admin,'Banco Ganadero','2026-10-01','2026-10-31');
    }
    public function test_report_is_durable_idempotent_and_late_receipt_survives_ai_failure(): void
    {
        Http::preventStrayRequests(); Storage::fake('minio-media'); Storage::fake('membership-private');
        app(MembershipBankQr::class)->save(UploadedFile::fake()->image('qr.png',400,400),$this->admin());
        $buyer=User::factory()->create(); $this->actingAs($buyer)->get('/membresia/checkout')->assertOk();
        $p=MembershipBankPayment::sole();
        $this->post('/membresia/checkout/aviso')->assertRedirect(); $at=$p->fresh()->reported_at;
        $this->post('/membresia/checkout/aviso')->assertRedirect();
        $this->get('/membresia/checkout')->assertSee('¡Tu aviso de pago quedó registrado!')->assertSee('Adjunta tu comprobante de pago')->assertSee('Subir comprobante')->assertDontSee('Opcional')->assertDontSee('Pago confirmado');
        $this->assertSame('PAYMENT_REPORTED',$p->fresh()->status); $this->assertEquals($at,$p->fresh()->reported_at);
        $this->assertDatabaseCount('membership_bank_payments',1); $this->assertDatabaseCount('memberships',0); $this->assertDatabaseCount('commercial_outbox',0);
        $other=User::factory()->create(); $this->actingAs($other)->get('/membresia/checkout/comprobante/'.$p->id)->assertForbidden();
        $this->actingAs($buyer); $file=UploadedFile::fake()->image('recibo.png');
        $this->post('/membresia/checkout/comprobante',['receipt'=>$file])->assertRedirect(); $p->refresh();
        (new ExtractMembershipReceipt($p->id,$p->extraction_version))->handle(app(\App\Services\MembershipReceiptVision::class));
        $this->assertSame('unconfigured',$p->fresh()->extraction_status);
        Storage::disk('minio-media')->assertExists($p->receipt_path); $this->assertEquals($at,$p->fresh()->reported_at);
        $this->post('/membresia/checkout/comprobante',['receipt'=>$file])->assertRedirect();
        $this->assertDatabaseCount('membership_bank_payments',1); $this->assertDatabaseCount('commercial_outbox',0); $this->assertDatabaseCount('reward_transactions',0);
    }
    public function test_preview_then_confirm_retains_debits_overlap_and_independent_history(): void
    {
        Storage::fake('membership-private'); $a=$this->admin(); $svc=app(MembershipBankImport::class);
        $rows="2026-10-29,10:00,A,Abono,0,100,1000\n2026-10-29,10:01,B,Otro pago,0,100,1100\n2026-10-29,10:02,D,Pago,50,0,1050\n";
        $id=$this->preview($this->file($rows),$a); $this->assertDatabaseCount('membership_bank_imports',0);
        $this->actingAs($a)->get('/admin/extractos-bancarios/preview/'.$id)->assertOk()->assertSee('1050.00');
        $this->post('/admin/extractos-bancarios/preview/'.$id.'/confirmar')->assertSessionHasErrors('validated');
        $r=$svc->confirm($id,$a); $this->assertSame(3,$r['imported']);
        $this->assertTrue($svc->confirm($id,$a)['duplicate']);
        $id2=$this->preview($this->file($rows."2026-10-30,10:00,C,Nuevo,0,100,1150\n"),$a);
        $r2=$svc->confirm($id2,$a); $this->assertSame(1,$r2['imported']);
        $this->assertDatabaseCount('membership_bank_movements',4); $this->assertDatabaseCount('bank_statement_entries',7);
        $this->assertSame(3,DB::table('membership_bank_imports')->where('id',$r2['id'])->value('duplicate_count'));
        $this->get('/admin/extractos-bancarios/'.$r['id'])->assertOk()->assertSee('0 conciliados')->assertSee('3 pendientes');
        $this->get('/admin/extractos-bancarios/'.$r['id'].'/original')->assertOk()->assertDownload();
        $p=app(MembershipBankCheckout::class)->start(User::factory()->create()); app(MembershipBankCheckout::class)->report($p);
        $mid=DB::table('membership_bank_movements')->where('reference','A')->value('id');
        app(MembershipBankCheckout::class)->approve($p->id,$mid,$a);
        $this->get('/admin/extractos-bancarios/'.$r['id'])->assertOk()->assertSee('1 conciliados')->assertSee('2 pendientes');
        $this->get('/admin/extractos-bancarios/'.$r2['id'])->assertOk()->assertSee('1 conciliados');
        $this->assertDatabaseCount('commercial_outbox',1); $this->assertDatabaseCount('reward_transactions',0);
        $this->actingAs(User::factory()->create())->get('/admin/extractos-bancarios')->assertForbidden();
    }
    public function test_equal_values_different_times_and_ambiguous_rows_do_not_merge(): void
    {
        Storage::fake('membership-private'); $a=$this->admin(); $svc=app(MembershipBankImport::class);
        $id=$this->preview($this->file("2026-10-29,10:00,SAME,Abono,0,100,1000\n2026-10-29,10:01,SAME,Abono,0,100,1100\n2026-10-29,,,Sin identidad,0,100,\n2026-10-29,,,Sin identidad,0,100,\n"),$a);
        // A missing balance is unknown, never manufactured.
        $p=DB::table('bank_statement_previews')->where('id',$id)->first();
        $this->assertNotSame('[]',$p->incidents);
        $id=$this->preview($this->file("2026-10-29,10:00,SAME,Abono,0,100,1000\n2026-10-29,10:01,SAME,Abono,0,100,1100\n2026-10-29,,,Sin identidad,0,100,1200\n2026-10-29,,,Sin identidad,0,100,1200\n"),$a);
        $this->assertSame(4,$svc->confirm($id,$a)['imported']);
        $this->assertSame(2,DB::table('membership_bank_movements')->where('reconciliation_hold',true)->count());
        $p=app(MembershipBankCheckout::class)->start(User::factory()->create()); $svcCheckout=app(MembershipBankCheckout::class); $svcCheckout->report($p);
        $held=DB::table('membership_bank_movements')->where('reconciliation_hold',true)->value('id');
        $this->actingAs($a)->post('/admin/conciliacion-membresias/aprobar',['payment_id'=>$p->id,'movement_id'=>$held])->assertStatus(422);
    }
    public function test_pdf_and_xlsx_are_locally_extracted_without_network(): void
    {
        Http::preventStrayRequests();
        $parser=app(BankStatementParser::class);
        $pdf=$this->pdf('Fecha          Hora     Referencia     Descripcion     Debito     Credito     Saldo', '29/10/2026     10:00    ABC            Abono           0.00       100.00      1000.00');
        $rows=$parser->parse(UploadedFile::fake()->createWithContent('ganadero.pdf',$pdf));
        $this->assertCount(1,$rows); $this->assertSame('100.00',$rows[0]['credit']); $this->assertSame('ABC',$rows[0]['reference']);
        $path=tempnam(sys_get_temp_dir(),'xlsx'); $zip=new \ZipArchive; $zip->open($path,\ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml','<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Movimientos" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $xml='<worksheet><sheetData>'; foreach([['Fecha','Hora','Referencia','Descripcion','Debito','Credito','Saldo'],['29/10/2026','10:00','ABC','Abono','0','100','1000']] as $n=>$row) { $xml.='<row>'; foreach($row as $i=>$v){$xml.='<c r="'.chr(65+$i).($n+1).'" t="inlineStr"><is><t>'.$v.'</t></is></c>';}$xml.='</row>'; } $xml.='</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml',$xml); $zip->close();
        try {$xlsx=UploadedFile::fake()->createWithContent('ganadero.xlsx',file_get_contents($path)); $xlsxRows=$parser->parse($xlsx); $this->assertSame($rows[0]['paid_date'],$xlsxRows[0]['paid_date']); $this->assertSame($rows[0]['identity'],$xlsxRows[0]['identity']); $this->assertStringContainsString('<row>',$xlsxRows[0]['original_row']);} finally {unlink($path);}
        Storage::fake('membership-private'); $a=$this->admin();
        $id=$this->preview(UploadedFile::fake()->createWithContent('scanned.pdf',$this->pdf('','')),$a);
        $this->actingAs($a)->get('/admin/extractos-bancarios/preview/'.$id)->assertOk()->assertSee('OCR local no disponible');
        $this->post('/admin/extractos-bancarios/preview/'.$id.'/confirmar',['validated'=>1])->assertSessionHasErrors('statement');
        $this->assertDatabaseCount('membership_bank_imports',0);
    }
    public function test_ganamovil_multiline_profile_metadata_and_overlap(): void
    {
        Http::preventStrayRequests(); Storage::fake('membership-private');
        $file=UploadedFile::fake()->createWithContent('ganamovil.pdf',$this->ganaMovilPdf());
        $doc=app(BankStatementParser::class)->parseDocument($file);
        $this->assertCount(2,$doc['rows']);
        $this->assertSame('1234567890',$doc['metadata']['account']);
        $this->assertSame('2026-10-31 20:18:05',$doc['metadata']['generated_at']);
        $this->assertTrue($doc['metadata']['balance_verified']);
        $this->assertSame('700001',$doc['rows'][0]['reference']);
        $this->assertStringContainsString("Nro. 990001\nBANCO DE PRUEBA\n555000\nPERSONA FICTICIA\nConcepto ficticio",$doc['rows'][0]['description']);
        $this->assertSame('-25.00',$doc['rows'][1]['amount']);
        $this->assertSame('75.00',$doc['rows'][1]['balance']);
        $a=$this->admin(); $svc=app(MembershipBankImport::class);
        $id=$this->preview($file,$a);
        $this->actingAs($a)->get('/admin/extractos-bancarios/preview/'.$id)->assertOk()->assertSee('1234567890')->assertSee('Saldos y cantidades verificados');
        $r=$svc->confirm($id,$a); $this->assertSame(2,$r['imported']);
        $this->assertTrue($svc->confirm($id,$a)['duplicate']);
        $overlap=UploadedFile::fake()->createWithContent('overlap.pdf',$this->ganaMovilPdf('8:19:05 PM'));
        $r2=$svc->confirm($this->preview($overlap,$a),$a);
        $this->assertSame(0,$r2['imported']);
        $this->assertSame(2,DB::table('membership_bank_imports')->where('id',$r2['id'])->value('duplicate_count'));
        $this->assertSame('1234567890',json_decode(DB::table('membership_bank_imports')->where('id',$r['id'])->value('document_metadata'),true)['account']);
        $this->assertDatabaseCount('membership_bank_payments',0); $this->assertDatabaseCount('memberships',0);
        $this->assertDatabaseCount('commercial_outbox',0); $this->assertDatabaseCount('reward_transactions',0);
        foreach ([['1234567899','Banco Ganadero'],['GANADERO-BOB','Otro Banco']] as [$account,$bank]) {
            $id=$svc->preview($file,$account,$a,$bank,'2026-10-01','2026-10-31');
            $this->assertNotSame('[]',DB::table('bank_statement_previews')->where('id',$id)->value('incidents'));
        }
    }

    public function test_ganamovil_corruption_blocks_all_movements(): void
    {
        Storage::fake('membership-private'); $a=$this->admin();
        foreach (['balance','count','page','truncated','unknown'] as $corruption) {
            $id=$this->preview(UploadedFile::fake()->createWithContent('bad.pdf',$this->ganaMovilPdf('8:18:05 PM',$corruption)),$a);
            $p=DB::table('bank_statement_previews')->where('id',$id)->first();
            $this->assertSame('[]',$p->rows,$corruption); $this->assertNotSame('[]',$p->incidents,$corruption);
            $this->actingAs($a)->post('/admin/extractos-bancarios/preview/'.$id.'/confirmar',['validated'=>1])->assertSessionHasErrors('statement');
        }
        $this->assertDatabaseCount('membership_bank_imports',0); $this->assertDatabaseCount('membership_bank_movements',0);
    }

    /** Entirely invented data; no sample bytes or personal banking information. */
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

    public function test_mobile_admin_upload_detects_metadata_and_duplicates_without_activation(): void
    {
        Http::preventStrayRequests(); Storage::fake('membership-private');
        $file=UploadedFile::fake()->createWithContent('movil.pdf',$this->ganaMovilPdf());
        $this->get('/admin/extractos-bancarios')->assertRedirect('/login');
        $this->post('/admin/extractos-bancarios/importar',['statement'=>$file])->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/extractos-bancarios')->assertForbidden();
        $this->post('/admin/extractos-bancarios/importar',['statement'=>$file])->assertForbidden();
        $this->actingAs($this->admin());
        $this->get('/admin/conciliacion-membresias')->assertSee('Extractos bancarios');
        $this->get('/admin/extractos-bancarios')->assertOk()->assertSee('Importar y procesar')->assertSee('accept=".pdf,application/pdf"',false);
        $this->post('/admin/extractos-bancarios/importar',['statement'=>$file])->assertRedirect();
        $import=DB::table('membership_bank_imports')->sole();
        $this->assertSame('1234567890',$import->account_key);
        $this->assertSame('2026-10-01',$import->period_start);
        Storage::disk('membership-private')->assertExists($import->path);
        $this->get('/admin/extractos-bancarios/'.$import->id)->assertOk()->assertSee('movil.pdf')->assertSee('Movimientos nuevos')->assertSee('data-label="Saldo"',false)->assertSee('Conciliación de créditos de Bs 100');
        $this->post('/admin/extractos-bancarios/importar',['statement'=>$file])->assertSessionHas('status','Este PDF ya fue importado. No se duplicaron movimientos.');
        $this->assertDatabaseCount('membership_bank_imports',1); $this->assertDatabaseCount('membership_bank_movements',2);
        $second=UploadedFile::fake()->createWithContent('solapado.pdf',$this->ganaMovilPdf('8:19:05 PM'));
        $this->post('/admin/extractos-bancarios/importar',['statement'=>$second])->assertRedirect();
        $latest=DB::table('membership_bank_imports')->orderByDesc('id')->first();
        $this->assertSame(2,$latest->duplicate_count);
        $this->get('/admin/extractos-bancarios/'.$latest->id)->assertSee('El período coincide con');
        $this->assertDatabaseCount('membership_bank_payments',0); $this->assertDatabaseCount('memberships',0); $this->assertDatabaseCount('commercial_outbox',0);
        $this->actingAs(User::factory()->create())->get('/admin/extractos-bancarios/'.$import->id.'/original')->assertForbidden();
    }

    public function test_mobile_upload_invalid_files_fail_without_movements(): void
    {
        Storage::fake('membership-private'); $this->actingAs($this->admin());
        foreach ([UploadedFile::fake()->createWithContent('fake.pdf','not a pdf'), UploadedFile::fake()->createWithContent('empty.pdf',$this->pdf('','')),UploadedFile::fake()->create('large.pdf',10241,'application/pdf')] as $file) {
            $this->post('/admin/extractos-bancarios/importar',['statement'=>$file])->assertSessionHasErrors('statement');
        }
        $this->assertDatabaseCount('membership_bank_imports',0); $this->assertDatabaseCount('membership_bank_movements',0);
    }

    public function test_mobile_candidates_reuse_match_and_require_existing_approval(): void
    {
        Http::preventStrayRequests(); Storage::fake('membership-private'); $this->actingAs($this->admin());
        $this->post('/admin/extractos-bancarios/importar',['statement'=>UploadedFile::fake()->createWithContent('movil.pdf',$this->ganaMovilPdf())])->assertRedirect();
        $import=DB::table('membership_bank_imports')->sole();
        $p=app(MembershipBankCheckout::class)->start(User::factory()->create());
        $p->forceFill(['status'=>'under_review','declared_reference'=>'700001','paid_date'=>'2026-10-01'])->save();
        $this->get('/admin/extractos-bancarios/'.$import->id)->assertOk()->assertSee($p->reference)->assertSee('Las coincidencias son candidatas');
        $this->assertDatabaseCount('memberships',0); $this->assertDatabaseCount('commercial_outbox',0);
        $mid=DB::table('membership_bank_movements')->where('reference','700001')->value('id');
        $this->post('/admin/conciliacion-membresias/aprobar',['payment_id'=>$p->id,'movement_id'=>$mid])->assertRedirect();
        $this->assertDatabaseCount('memberships',1); $this->assertDatabaseCount('commercial_outbox',1);
        $this->get('/admin/extractos-bancarios/'.$import->id)->assertSee('1 conciliados');
    }

    private function pdf(string $header,string $line): string
    {
        $stream="BT /F1 9 Tf 30 800 Td ($header) Tj 0 -20 Td ($line) Tj ET";
        $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 1000 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>','<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream"];
        $pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n$object\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";foreach(array_slice($offsets,1) as $offset){$pdf.=sprintf("%010d 00000 n \n",$offset);}return $pdf."trailer << /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
