@extends('membership-bank.layout')
@section('title','Historial de extractos bancarios')
@section('content')
<a href="/admin/conciliacion-membresias">← Conciliación de membresías</a><h1>Historial de extractos bancarios</h1>
<p>Archivo permanente e independiente de la conciliación. Importar no confirma pagos ni activa membresías.</p>
<section class="card"><h2>Previsualizar extracto</h2>
<p>CSV UTF-8, XLSX de una hoja o PDF digital. Lectura local sin IA. Un PDF con diseño no reconocido requiere exportación tabular del banco. Los escaneados se señalan si no hay OCR local.</p>
<details><summary>Columnas admitidas</summary><p>Fecha, Hora, Referencia (o Documento), Descripción (o Glosa), Débito, Crédito, Saldo. Fechas YYYY-MM-DD o DD/MM/YYYY. XLSX: fechas y horas como texto, sin fórmulas. También se conserva el CSV existente: date,time,reference,description,amount,direction.</p><p>Use el mismo identificador de cuenta en extractos superpuestos. Las filas sin suficiente identidad se conservan retenidas, sin conciliación automática.</p></details>
<form method="post" action="/admin/extractos-bancarios/previsualizar" enctype="multipart/form-data">@csrf
<label>Banco</label><select name="bank"><option>Banco Ganadero</option></select>
<label>Identificador estable de cuenta</label><input name="account_key" value="{{old('account_key')}}" pattern="[A-Z0-9_-]{1,100}" required>
<label>Desde</label><input type="date" name="period_start" value="{{old('period_start')}}" required><label>Hasta</label><input type="date" name="period_end" value="{{old('period_end')}}" required>
<label>Extracto original</label><input type="file" name="statement" accept=".csv,.xlsx,.pdf" required><button>Previsualizar y validar</button></form></section>
<div class="card table"><table><thead><tr><th>Archivo / SHA-256</th><th>Banco / cuenta / período</th><th>Importación / administrador</th><th>Estado / extraídos / duplicados</th></tr></thead><tbody>
@foreach($imports as $i)<tr><td><a href="/admin/extractos-bancarios/{{$i->id}}">{{$i->original_name??'Extracto histórico #'.$i->id}}</a><small>{{$i->sha256}}</small></td><td>{{$i->bank??'Banco no registrado (histórico)'}} · {{$i->account_key}}<small>{{$i->period_start}} — {{$i->period_end}}</small></td><td>{{$i->created_at}} UTC<small>{{$i->administrator}}</small></td><td>{{$i->processing_status}}<small>{{$i->extracted_count}} extraídos · {{$i->duplicate_count}} duplicados</small></td></tr>@endforeach
</tbody></table>{{$imports->links()}}</div>
<section class="card"><h2>Mis previsualizaciones conservadas</h2>@foreach($previews as $p)<p><a href="/admin/extractos-bancarios/preview/{{$p->id}}">{{$p->original_name}} · {{$p->created_at}} UTC</a></p>@endforeach</section>
@endsection
