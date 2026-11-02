@extends('membership-bank.admin-layout')
@section('title','Extractos bancarios')
@section('content')
<a href="/admin/conciliacion-membresias">← Pagos y conciliación</a><h1>Extractos bancarios</h1>
<section class="card" id="importar"><h2>Importar extracto bancario</h2>
<p>Selecciona el PDF digital descargado de GanaMóvil, Banco Ganadero. Leeremos la cuenta y el período automáticamente.</p>
<form id="statement-upload" method="post" action="/admin/extractos-bancarios/importar" enctype="multipart/form-data">@csrf
<label for="statement">Seleccionar PDF</label><input id="statement" name="statement" type="file" accept=".pdf,application/pdf" required aria-describedby="pdf-help">
<p id="pdf-help" class="muted">Hasta 10 MB. El archivo se guarda de forma privada. Las coincidencias bancarias seguras se concilian y activan automáticamente.</p>
<button id="import-button" type="submit">Importar extracto bancario</button>
<p id="upload-status" role="status" aria-live="polite" hidden>Subiendo y procesando PDF… Mantén esta pantalla abierta.</p>
</form></section>
<h2>Pagos JAKAWI identificados</h2>
<p>Lista acumulativa de créditos cuyo concepto contiene JAKAWI. La conciliación se procesa automáticamente al importar y al recibir evidencia tardía.</p>
<nav class="statement-summary" aria-label="Estado de pagos">
<a class="card" href="/admin/extractos-bancarios">Todos · {{$counts->sum()}}</a>
<a class="card" href="?estado=auto_reconciled">Conciliados automáticamente · {{$counts['auto_reconciled']??0}}</a>
<a class="card" href="?estado=unidentified">Sin identificar · {{$counts['unidentified']??0}}</a>
<a class="card" href="?estado=review_required">Requieren revisión · {{($counts['review_required']??0)+($counts['unidentified']??0)}}</a>
</nav>
@forelse($jakawi as $j)
<article class="card">
<h3>Bs {{number_format((float)$j->credit,2)}} · {{$j->paid_date}} {{$j->paid_time}}</h3>
<p><span class="badge {{ in_array($j->reconciliation_status, ['auto_reconciled', 'reconciled'], true) ? 'admin-status-success' : 'admin-status-warning' }}">{{['auto_reconciled'=>'Conciliado automáticamente','reconciled'=>'Conciliado','unidentified'=>'Sin identificar · Requiere revisión','review_required'=>'Requiere revisión'][$j->reconciliation_status]}}</span></p>
<p>Referencia bancaria: {{$j->reference??'Sin referencia'}} · <a href="/admin/extractos-bancarios/{{$j->import_id}}">Extracto #{{$j->import_id}}</a></p>
<p class="movement-description">{{$j->description}}</p>
@if($j->purchase_reference)<p>Compra: <a href="/admin/conciliacion-membresias">{{$j->purchase_reference}}</a> · Usuario: {{$j->buyer??$j->buyer_name}}</p>@endif
<p>{{$j->reason}}</p>
<details><summary>Detalle y evidencia de la decisión</summary><pre>{{json_encode(json_decode($j->evidence,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)}}</pre></details>
</article>
@empty<p class="card">No hay pagos JAKAWI en este estado.</p>@endforelse
{{$jakawi->links()}}
<details><summary>Historial bancario completo — todos los movimientos y PDFs</summary>
<h2 id="historial">Historial de extractos</h2>
<div class="statement-history">
@forelse($imports as $i)
<article class="card"><h3><a href="/admin/extractos-bancarios/{{$i->id}}">{{$i->original_name??'Extracto histórico #'.$i->id}}</a></h3>
<p>{{$i->bank??'Banco histórico'}} · Cuenta {{$i->account_key}}</p><p>{{$i->period_start}} — {{$i->period_end}}</p>
<p>Importado: {{\Carbon\CarbonImmutable::parse($i->created_at,'UTC')->timezone('America/La_Paz')->format('d/m/Y H:i')}} · {{$i->administrator}}</p>
<p>{{$i->extracted_count}} movimientos · {{$i->duplicate_count}} duplicados</p><span class="badge {{ $i->processing_status === 'processed' ? 'admin-status-success' : 'admin-status-neutral' }}">{{$i->processing_status==='processed'?'Procesado':'Histórico'}}</span></article>
@empty<p class="card">Aún no hay extractos importados.</p>@endforelse
</div>{{$imports->links()}}
</details>
@if($previews->isNotEmpty())<details><summary>Previsualizaciones anteriores</summary>@foreach($previews as $p)<p><a href="/admin/extractos-bancarios/preview/{{$p->id}}">{{$p->original_name}} · {{$p->created_at}} UTC</a> @if(json_decode($p->incidents,true))<span class="badge admin-status-warning">Requiere revisión · PDF con incidencias</span>@endif</p>@endforeach</details>@endif
<script>
document.getElementById('statement-upload').addEventListener('submit',function(){
 document.getElementById('import-button').disabled=true;
 document.getElementById('upload-status').hidden=false;
 this.setAttribute('aria-busy','true');
});
window.addEventListener('pageshow',function(){document.getElementById('import-button').disabled=false;document.getElementById('upload-status').hidden=true;document.getElementById('statement-upload').removeAttribute('aria-busy');});
</script>
@endsection
