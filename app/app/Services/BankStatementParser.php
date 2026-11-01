<?php
namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

/** Deterministic extraction only. Unknown layouts fail closed for manual correction. */
class BankStatementParser
{
    private array $metadata = [];

    /** Metadata is evidence from the document, never inferred from counterparties. */
    public function parseDocument(UploadedFile $file): array
    {
        $rows = $this->parse($file);
        return ['rows' => $rows, 'metadata' => $this->metadata];
    }

    public function parse(UploadedFile $file): array
    {
        $this->metadata = [];
        $ext = strtolower($file->getClientOriginalExtension());
        $lines = match ($ext) {
            'csv', 'txt' => $this->csv($file->getRealPath()),
            'xlsx' => $this->xlsx($file->getRealPath()),
            'pdf' => $this->pdf($file->getRealPath()),
            default => $this->invalid('Formato no admitido. Use CSV, XLSX o PDF digital.'),
        };
        $aliases = [
            'date'=>['date','fecha','fecha movimiento'], 'time'=>['time','hora'],
            'reference'=>['reference','referencia','nro documento','nro. documento','documento','nro operacion','nro. operacion','operacion','numero de documento'],
            'description'=>['description','descripcion','detalle','concepto','glosa'],
            'amount'=>['amount','importe','monto'], 'direction'=>['direction','direccion'],
            'debit'=>['debit','debito','debitos','retiros','debe'], 'credit'=>['credit','credito','creditos','abonos','haber'],
            'balance'=>['balance','saldo'],
        ];
        $map = null; $rows = []; $occurrences = [];
        foreach ($lines as $n => $line) {
            $cells=$line['cells']??$line; $original=$line['original']??null;
            if ($n > 11000) { $this->invalid('Límite de 10.000 movimientos excedido.'); }
            $normalized = array_map(fn($s)=>trim(strtolower(iconv('UTF-8','ASCII//TRANSLIT', $s))), $cells);
            $candidate = [];
            foreach ($aliases as $field=>$names) {
                foreach ($normalized as $i=>$name) { if (in_array($name,$names,true)) { $candidate[$field]=$i; break; } }
            }
            if (isset($candidate['date'], $candidate['description']) && (isset($candidate['amount'],$candidate['direction']) || isset($candidate['debit'],$candidate['credit'],$candidate['balance']))) {
                $map=$candidate; continue; // Repeated PDF page headers.
            }
            if (! $map || ! array_filter($cells, fn($c)=>trim($c)!=='')) { continue; }
            $get=fn($key)=>trim($cells[$map[$key]]??'');
            $date=$get('date');
            // Only known page furniture is ignored after the header, never an unparsed transaction.
            if (preg_match('/\A(?:p[aá]gina\b|banco ganadero\b|saldo (?:anterior|inicial|final)\b|total(?:es)?\b)/iu', trim(implode(' ', $cells)))) { continue; }
            if (max($map) >= count($cells)) { $this->invalid('Fila '.($n+1).': columnas incompletas.'); }
            $format=preg_match('/\A\d{2}\/\d{2}\/\d{4}\z/', $date) ? 'd/m/Y' : 'Y-m-d';
            if (! CarbonImmutable::hasFormat($date,$format)) { $this->invalid('Fila '.($n+1).': fecha inválida o formato PDF no reconocido.'); }
            $date=CarbonImmutable::createFromFormat('!'.$format,$date)->format('Y-m-d');
            $time=$get('time');
            if ($time!=='' && (!preg_match('/\A\d{2}:\d{2}(:\d{2})?\z/',$time) || !CarbonImmutable::hasFormat($time,strlen($time)===5?'H:i':'H:i:s'))) { $this->invalid('Hora inválida.'); }
            $time=$time===''?null:(strlen($time)===5?$time.':00':$time);
            $balance=isset($map['balance'])?$this->money($get('balance'),true):null;
            if (isset($map['amount'],$map['direction'])) {
                $direction=$get('direction');
                if (!in_array($direction,['credit','debit'],true)) { $this->invalid('direction debe ser credit o debit.'); }
                $amount=$this->money($get('amount'));
                $debit=$direction==='debit'?$amount:'0.00'; $credit=$direction==='credit'?$amount:'0.00';
            } else {
                $debit=$this->money($get('debit')?:'0'); $credit=$this->money($get('credit')?:'0');
                if (((float)$debit>0) === ((float)$credit>0)) { $this->invalid('Cada fila debe tener un débito o crédito positivo.'); }
                $amount=(float)$credit>0?$credit:'-'. $debit;
            }
            if ((float)$amount==0 || mb_strlen($get('reference'))>255 || mb_strlen($get('description'))>2000) { $this->invalid('Importe o longitud inválida.'); }
            if ((float)$debit>0) { $amount='-'.$debit; }
            $ref=MembershipBankCheckout::reference($get('reference'));
            $row=['paid_date'=>$date,'paid_time'=>$time,'reference'=>$ref,'description'=>$get('description'),'amount'=>$amount,'debit'=>$debit,'credit'=>$credit,'balance'=>$balance,'original_row'=>$original??json_encode($cells,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
            // Reference + time + balance distinguish genuine payments of equal value.
            $identity=[$date,$time,$ref,$amount,$balance,$ref===null?$row['description']:null];
            $key=hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
            $row['identity']=$key; $row['occurrence']=$occurrences[$key]=($occurrences[$key]??0)+1;
            $row['weak_identity']=$ref===null && ($time===null || $balance===null);
            $rows[]=$row;
            if (count($rows)>10000) { $this->invalid('Límite de 10.000 movimientos excedido.'); }
        }
        if (!$map || !$rows) { $this->invalid('No se reconocieron movimientos. Revise columnas y formato; no se incorporó ningún movimiento.'); }
        return $rows;
    }

    private function money(string $value, bool $signed=false): string
    {
        $value=trim(str_replace(["\xc2\xa0",' '],'',$value));
        if (preg_match('/\A-?\d{1,3}(,\d{3})+\.\d{2}\z/',$value)) { $value=str_replace(',','',$value); }
        elseif (preg_match('/\A-?\d{1,3}(\.\d{3})+,\d{2}\z/',$value)) { $value=str_replace(',','.',str_replace('.','',$value)); }
        elseif (preg_match('/\A-?\d+,\d{2}\z/',$value)) { $value=str_replace(',','.',$value); }
        if (!preg_match($signed?'/\A-?\d{1,10}(\.\d{1,2})?\z/':'/\A\d{1,10}(\.\d{1,2})?\z/',$value)) { $this->invalid('Importe inválido o separadores ambiguos.'); }
        return number_format((float)$value,2,'.','');
    }

    private function csv(string $path): array
    {
        $h=fopen($path,'r'); $first=fgets($h); rewind($h);
        $delimiter=substr_count($first,';')>substr_count($first,',')?';':',';
        $rows=[];
        try { while (($row=fgetcsv($h,0,$delimiter,'"',''))!==false) {
            $row=array_map(fn($v)=>ltrim((string)$v,"\xEF\xBB\xBF"),$row); $rows[]=$row;
            if(count($rows)>11000) {$this->invalid('Demasiadas filas.');}
        }} finally { fclose($h); }
        return $rows;
    }

    private function xml(string $bytes): \SimpleXMLElement
    {
        if (stripos($bytes,'<!DOCTYPE')!==false || stripos($bytes,'<!ENTITY')!==false) {$this->invalid('XML no permitido.');}
        $xml=@simplexml_load_string($bytes,\SimpleXMLElement::class,LIBXML_NONET);
        if (!$xml) {$this->invalid('XLSX inválido.');} return $xml;
    }

    private function xlsx(string $path): array
    {
        $z=new \ZipArchive;
        if ($z->open($path)!==true) {$this->invalid('XLSX inválido.');}
        try {
            $total=0;
            for($i=0;$i<$z->numFiles;$i++) { $total+=$z->statIndex($i)['size']; }
            if($total>30*1024*1024 || $z->numFiles>1000) {$this->invalid('XLSX excede límite descomprimido.');}
            $strings=[]; $shared=$z->getFromName('xl/sharedStrings.xml');
            if($shared!==false) { foreach($this->xml($shared)->si as $si) { $s=''; foreach($si->xpath('.//*[local-name()="t"]') as $t) {$s.=(string)$t;} $strings[]=$s; } }
            $book=$this->xml($z->getFromName('xl/workbook.xml')?:'');
            if(count($book->sheets->sheet)!==1) {$this->invalid('XLSX: exporte una sola hoja de movimientos para evitar omisiones.');}
            $sheet=$book->sheets->sheet[0]; $rid=(string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            $rels=$this->xml($z->getFromName('xl/_rels/workbook.xml.rels')?:''); $target=null;
            foreach($rels->Relationship as $rel) { if((string)$rel['Id']===$rid) {$target=(string)$rel['Target'];} }
            if(!$target || str_contains($target,'..')) {$this->invalid('Hoja XLSX inválida.');}
            $target=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target;
            $styles=[]; $formats=[]; $styleBytes=$z->getFromName('xl/styles.xml');
            if($styleBytes!==false) {
                $styleXml=$this->xml($styleBytes);
                foreach($styleXml->numFmts->numFmt??[] as $f) { $formats[(int)$f['numFmtId']]=(string)$f['formatCode']; }
                foreach($styleXml->cellXfs->xf??[] as $xf) { $styles[]=(int)$xf['numFmtId']; }
            }
            if((string)($book->workbookPr['date1904']??'0')==='1') {$this->invalid('XLSX con calendario 1904: exporte fechas como texto.');}
            $xml=$this->xml($z->getFromName($target)?:''); $rows=[];
            foreach($xml->sheetData->row as $row) {
                $cells=[];
                foreach($row->c as $c) {
                    if(isset($c->f)) {$this->invalid('XLSX con fórmulas: exporte valores.');}
                    preg_match('/\A([A-Z]+)\d+\z/',(string)$c['r'],$m); $col=0;
                    foreach(str_split($m[1]??'A') as $char) {$col=$col*26+ord($char)-64;}
                    if($col>30) {$this->invalid('Demasiadas columnas.');}
                    $v=(string)$c->v;
                    if((string)$c['t']==='s') {$v=$strings[(int)$v]??'';}
                    if((string)$c['t']==='inlineStr') {$v=''; foreach($c->xpath('.//*[local-name()="t"]') as $t) {$v.=(string)$t;}}
                    $style=$styles[(int)($c['s']??0)]??0;
                    $format=$formats[$style]??'';
                    if(is_numeric($v) && !in_array((string)$c['t'],['s','inlineStr'],true)) {
                        $isTime=in_array($style,[18,19,20,21,45,46,47],true) || ($format!=='' && preg_match('/[hs]/i',$format) && !preg_match('/[dy]/i',$format));
                        $isDate=in_array($style,[14,15,16,17,22],true) || ($format!=='' && preg_match('/[dy]/i',$format));
                        if($isDate) { if((float)$v<61 || (float)$v>100000) {$this->invalid('Fecha Excel fuera de rango.');} $v=CarbonImmutable::create(1899,12,30)->addDays((int)(float)$v)->format('Y-m-d'); }
                        elseif($isTime) { if((float)$v<0 || (float)$v>=1) {$this->invalid('Hora Excel fuera de rango.');} $seconds=(int)round((float)$v*86400); $v=sprintf('%02d:%02d:%02d',intdiv($seconds,3600),intdiv($seconds%3600,60),$seconds%60); }
                    }
                    $cells[$col-1]=$v;
                }
                if($cells) { $filled=array_fill(0,max(array_keys($cells))+1,''); foreach($cells as $i=>$v){$filled[$i]=$v;} $rows[]=['cells'=>$filled,'original'=>$row->asXML()]; }
                if(count($rows)>11000) {$this->invalid('Demasiadas filas.');}
            }
            return $rows;
        } finally {$z->close();}
    }

    private function pdf(string $path): array
    {
        $process=new Process(['pdftotext','-layout','-enc','UTF-8',$path,'-']); $process->setTimeout(30);
        try { $process->run(); } catch (\Throwable) {$this->invalid('Poppler no disponible o extracción excedió tiempo.');}
        if(!$process->isSuccessful()) {$this->invalid('PDF ilegible, protegido o dañado.');}
        $text=$process->getOutput();
        if(strlen($text)>8*1024*1024) {$this->invalid('PDF textual demasiado grande.');}
        if(trim(str_replace("\f", '', $text))==='') {$this->invalid('PDF escaneado: OCR local no disponible en esta versión. Adjunte CSV/XLSX del banco; no se usó IA.');}
        if (str_contains($text, 'Banco Ganadero S.A. - GanaMóvil') && str_contains($text, 'NRO. DE CUENTA:')) {
            return $this->ganaMovil($text);
        }
        $rows=[];
        foreach(preg_split('/\R/',str_replace("\f","\n",$text)) as $line) {
            $line=trim($line); if($line==='') {continue;}
            $rows[]=['cells'=>preg_split('/\s{2,}/u',$line),'original'=>$line];
        }
        return $rows;
    }

    /** GanaMóvil digital statement: multiline descriptions surround a single dated row.
     * All monetary values are checked in integer cents against the running balance.
     * Unknown furniture/blocks fail closed; nothing is silently dropped.
     */
    private function ganaMovil(string $text): array
    {
        $required = [
            'account' => '/NRO\. DE CUENTA:\s*(\d+)/u',
            'period' => '/PERIODO DEL (\d{2}\/\d{2}\/\d{4}) AL (\d{2}\/\d{2}\/\d{4})/u',
            'generated' => '/Fecha y Hora:\h*(\d{1,2}\/\d{1,2}\/\d{4})\h+(\d{1,2}:\d{2}:\d{2})\h+(AM|PM)\h*$/mu',
            'opening' => '/SALDO ANTERIOR:\s*(-?[\d,]+\.\d{2})/u',
            'closing' => '/SALDO ACTUAL\s*:\s*(-?[\d,]+\.\d{2})/u',
            'debits' => '/CANTIDAD DÉBITOS:\s*(\d+)/u',
            'credits' => '/CANTIDAD CRÉDITOS:\s*(\d+)/u',
        ];
        $found = [];
        foreach ($required as $key => $pattern) {
            if (($key === 'generated' && substr_count($text, 'Fecha y Hora:') !== 1)
                || preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) !== 1) {
                $this->invalid('GanaMóvil: metadatos ausentes o repetidos ('.$key.').');
            }
            $found[$key] = $matches[0];
        }
        if (!str_contains($text, 'EXPRESADO EN BOLIVIANOS')) {
            $this->invalid('GanaMóvil: moneda no admitida; se esperaba bolivianos.');
        }
        $isoDate = function (string $value): string {
            if (!CarbonImmutable::hasFormat($value, 'd/m/Y')) { $this->invalid('GanaMóvil: fecha inválida.'); }
            $date = CarbonImmutable::createFromFormat('!d/m/Y', $value);
            if ($date->format('d/m/Y') !== $value) { $this->invalid('GanaMóvil: fecha inexistente.'); }
            return $date->format('Y-m-d');
        };
        $generated = $found['generated'];
        // GanaMóvil prints the emission day/month without leading zeros. Normalize only after strict validation.
        [$day, $month, $year] = explode('/', $generated[1]);
        $generated[1] = sprintf('%02d/%02d/%s', (int)$day, (int)$month, $year);
        $clock = str_pad($generated[2], 8, '0', STR_PAD_LEFT).' '.$generated[3];
        if (!CarbonImmutable::hasFormat($clock, 'h:i:s A')) { $this->invalid('GanaMóvil: hora de emisión inválida.'); }
        $this->metadata = [
            'format' => 'banco-ganadero-ganamovil-digital-v1', 'bank' => 'Banco Ganadero',
            'account' => $found['account'][1], 'currency' => 'BOB',
            'period_start' => $isoDate($found['period'][1]), 'period_end' => $isoDate($found['period'][2]),
            'generated_at' => $isoDate($generated[1]).' '.CarbonImmutable::createFromFormat('!h:i:s A', $clock)->format('H:i:s'),
            'timezone' => 'America/La_Paz', 'timezone_source' => 'bank_locale_assumption',
            'opening_balance' => $this->money($found['opening'][1], true),
            'closing_balance' => $this->money($found['closing'][1], true),
            'debit_count' => (int)$found['debits'][1], 'credit_count' => (int)$found['credits'][1],
        ];
        if ($this->metadata['period_start'] > $this->metadata['period_end']) { $this->invalid('GanaMóvil: período inválido.'); }
        $cents = static fn(string $value): int => (int)str_replace('.', '', $value);
        $balance = $cents($this->metadata['opening_balance']);
        $counts = ['debit'=>0, 'credit'=>0];
        $rows = [['date','time','reference','description','debit','credit','balance']];
        $pages = explode("\f", $text);
        if (trim(end($pages)) === '') { array_pop($pages); }
        foreach ($pages as $pageIndex => $page) {
            if (preg_match_all('/Página (\d+) de (\d+)\s+La información del extracto está sujeta a verificación del Banco Ganadero\./u', $page, $footers, PREG_SET_ORDER) !== 1
                || (int)$footers[0][1] !== $pageIndex+1 || (int)$footers[0][2] !== count($pages)) {
                $this->invalid('GanaMóvil: páginas incompletas o fuera de orden.');
            }
            $page = str_replace($footers[0][0], '', $page);
            if ($pageIndex === 0) {
                if (!preg_match('/FECHA\s+HORA\s+OFICINA\/CANAL\s+TRANSACCIÓN\s+DESCRIPCIÓN\s+DÉBITO\s+CRÉDITO\s+SALDO/u', $page, $header, PREG_OFFSET_CAPTURE)) {
                    $this->invalid('GanaMóvil: cabecera no reconocida.');
                }
                $page = substr($page, $header[0][1] + strlen($header[0][0]));
            }
            if ($pageIndex === count($pages)-1) {
                // Only the exact, already validated summary is removed.
                $page = preg_replace('/^\h*(?:SALDO ACTUAL\s*:\s*-?[\d,]+\.\d{2}|CANTIDAD DÉBITOS:\s*\d+|CANTIDAD CRÉDITOS:\s*\d+)\h*$/mu', '', $page);
            }
            foreach (preg_split('/\n\h*\n/', trim($page, "\r\n")) as $block) {
                if (trim($block) === '') { continue; }
                $description = []; $transaction = null;
                foreach (preg_split('/\R/u', $block) as $line) {
                    if (trim($line) === '') { continue; }
                    if (preg_match('/^\h*(\d{2}\/\d{2}\/\d{4})\h+(\d{2}:\d{2}:\d{2})\h+(\S+)\h+(\d+)\h+(.*?)\h{2,}(-?[\d,]+\.\d{2})\h+(\+?[\d,]+\.\d{2})\h+(-?[\d,]+\.\d{2})\h*$/u', $line, $m)) {
                        if ($transaction !== null) { $this->invalid('GanaMóvil: bloque con varias transacciones.'); }
                        $transaction = $m;
                        if (trim($m[5]) !== '') { $description[] = trim($m[5]); }
                    } else {
                        // Continuations must remain inside the description column.
                        if (!preg_match('/^\h{20,}(\S.*)$/u', $line, $continuation)) { $this->invalid('GanaMóvil: fila no reconocida.'); }
                        $description[] = trim($continuation[1]);
                    }
                }
                if ($transaction === null || !$description) { $this->invalid('GanaMóvil: descripción o transacción incompleta.'); }
                $m = $transaction;
                $date = $isoDate($m[1]);
                if ($date < $this->metadata['period_start'] || $date > $this->metadata['period_end']) { $this->invalid('GanaMóvil: movimiento fuera del período del PDF.'); }
                $debit = $this->money(ltrim($m[6], '-'));
                $credit = $this->money(ltrim($m[7], '+'));
                $nextBalance = $this->money($m[8], true);
                if (($cents($debit)>0) === ($cents($credit)>0) || ($cents($debit)>0 && !str_starts_with($m[6], '-'))) {
                    $this->invalid('GanaMóvil: signos de débito/crédito inválidos.');
                }
                $balance += $cents($credit) - $cents($debit);
                if ($balance !== $cents($nextBalance)) { $this->invalid('GanaMóvil: saldo no cuadra con el movimiento.'); }
                $counts[$cents($debit)>0?'debit':'credit']++;
                $rows[] = ['cells'=>[$date,$m[2],$m[4],implode("\n",$description),$debit,$credit,$nextBalance], 'original'=>trim($block)];
                if (count($rows)>10001) { $this->invalid('Límite de 10.000 movimientos excedido.'); }
            }
        }
        if ($balance !== $cents($this->metadata['closing_balance']) || $counts['debit'] !== $this->metadata['debit_count'] || $counts['credit'] !== $this->metadata['credit_count']) {
            $this->invalid('GanaMóvil: totales o saldo final no coinciden.');
        }
        $this->metadata['pages'] = count($pages);
        $this->metadata['balance_verified'] = true;
        return $rows;
    }

    private function invalid(string $message): never { throw ValidationException::withMessages(['statement'=>$message]); }
}
