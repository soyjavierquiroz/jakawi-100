@extends('membership-bank.layout')
@section('title','Extractos bancarios')
@section('content')
<a href="/admin/conciliacion-membresias">← Conciliación de membresías</a><h1>Extractos bancarios</h1>
<section class="card"><h2>Subir extracto</h2>
<p>Selecciona el PDF digital descargado de GanaMóvil, Banco Ganadero. Leeremos la cuenta y el período automáticamente.</p>
<form id="statement-upload" method="post" action="/admin/extractos-bancarios/importar" enctype="multipart/form-data">@csrf
<label for="statement">Seleccionar PDF</label><input id="statement" name="statement" type="file" accept=".pdf,application/pdf" required aria-describedby="pdf-help">
<p id="pdf-help" class="muted">Hasta 10 MB. El archivo se guarda de forma privada. Importar no aprueba pagos.</p>
<button id="import-button" type="submit">Importar y procesar</button>
<p id="upload-status" role="status" aria-live="polite" hidden>Subiendo y procesando PDF… Mantén esta pantalla abierta.</p>
</form></section>
<h2>Historial de extractos</h2>
<div class="statement-history">
@forelse($imports as $i)
<article class="card"><h3><a href="/admin/extractos-bancarios/{{$i->id}}">{{$i->original_name??'Extracto histórico #'.$i->id}}</a></h3>
<p>{{$i->bank??'Banco histórico'}} · Cuenta {{$i->account_key}}</p><p>{{$i->period_start}} — {{$i->period_end}}</p>
<p>Importado: {{\Carbon\CarbonImmutable::parse($i->created_at,'UTC')->timezone('America/La_Paz')->format('d/m/Y H:i')}} · {{$i->administrator}}</p>
<p>{{$i->extracted_count}} movimientos · {{$i->duplicate_count}} duplicados</p><span class="badge">{{$i->processing_status==='processed'?'Procesado':'Histórico'}}</span></article>
@empty<p class="card">Aún no hay extractos importados.</p>@endforelse
</div>{{$imports->links()}}
@if($previews->isNotEmpty())<details><summary>Previsualizaciones anteriores</summary>@foreach($previews as $p)<p><a href="/admin/extractos-bancarios/preview/{{$p->id}}">{{$p->original_name}} · {{$p->created_at}} UTC</a></p>@endforeach</details>@endif
<script>
document.getElementById('statement-upload').addEventListener('submit',function(){
 document.getElementById('import-button').disabled=true;
 document.getElementById('upload-status').hidden=false;
 this.setAttribute('aria-busy','true');
});
window.addEventListener('pageshow',function(){document.getElementById('import-button').disabled=false;document.getElementById('upload-status').hidden=true;document.getElementById('statement-upload').removeAttribute('aria-busy');});
</script>
@endsection
