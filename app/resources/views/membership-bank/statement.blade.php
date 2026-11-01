@extends('membership-bank.layout')
@section('title','Extracto bancario')
@section('content')
<a href="/admin/extractos-bancarios">← Historial de extractos</a> · <a href="/admin/conciliacion-membresias">Conciliación operativa</a><h1>Extracto #{{$import->id}}</h1>
<p>{{$import->original_name}} · {{$import->bank??'Banco no registrado (histórico)'}} · {{$import->account_key}}</p><p>{{$import->period_start}} — {{$import->period_end}} · {{$import->created_at}} UTC · {{$administrator}}</p>
<p>SHA-256: {{$import->sha256}}</p><a href="/admin/extractos-bancarios/{{$import->id}}/original">Descargar original privado</a>
@include('membership-bank.document-metadata', ['metadata'=>json_decode($import->document_metadata??'[]',true)])
<p>Estado: {{$import->processing_status}} · {{$import->processing_status==='legacy'?$total:$import->extracted_count}} extraídos · {{$linked}} conciliados · {{$total-$linked}} pendientes · {{$import->duplicate_count}} duplicados</p>
@foreach(json_decode($import->incidents??'[]',true) as $incident)<p class="notice">{{$incident}}</p>@endforeach
<div class="card table"><table><thead><tr><th>Movimiento</th><th>Fecha / hora</th><th>Referencia / descripción</th><th>Débito / crédito / importe / saldo</th><th>Cuenta / estado / compra</th><th>Original</th></tr></thead><tbody>
@foreach($entries as $entry) @php($row=json_decode($entry->data,true))
<tr><td>#{{$entry->movement_id}}</td><td>{{$row['paid_date']}} {{$row['paid_time']}}</td><td>{{$row['reference']}}<small>{{$row['description']}}</small></td><td>{{$row['debit']}} / {{$row['credit']}} / {{$row['amount']}} / {{$row['balance']}}</td><td>{{$import->account_key}}<small>{{$entry->disposition}} · {{$entry->payment_reference?'Conciliado: '.$entry->payment_reference:'Sin comprador identificado'}}</small></td><td><details><summary>Fila original</summary><pre>{{$entry->original_row}}</pre></details></td></tr>
@endforeach
@if($import->processing_status==='legacy') @foreach($legacy as $row)<tr><td>#{{$row->id}}</td><td>{{$row->paid_date}} {{$row->paid_time}}</td><td>{{$row->reference}}<small>{{$row->description}}</small></td><td>{{$row->amount}}</td><td>{{$import->account_key}} · {{$row->payment_reference??'Pendiente'}}</td><td>Ver archivo original histórico</td></tr>@endforeach @endif
</tbody></table>{{$entries->links()}}@if($import->processing_status==='legacy'){{$legacy->links()}}@endif</div>
@endsection
