@extends('membership-bank.admin-layout')
@section('title','Extracto bancario')
@section('content')
<a href="/admin/extractos-bancarios">← Extractos bancarios</a><h1>Extracto #{{$import->id}}</h1>
<section class="card"><h2>{{$import->original_name??'Extracto histórico'}}</h2>
<p>{{$import->bank??'Banco no registrado (histórico)'}} · Cuenta {{$import->account_key}}</p>
<p>Período: {{$import->period_start}} — {{$import->period_end}}</p>
<p>Importado: {{\Carbon\CarbonImmutable::parse($import->created_at,'UTC')->timezone('America/La_Paz')->format('d/m/Y H:i')}} · {{$administrator}}</p>
<p class="badge">{{$import->processing_status==='processed'?'Procesado':'Histórico'}}</p>
<a class="button" href="/admin/extractos-bancarios/{{$import->id}}/original">Descargar PDF original privado</a>
<details><summary>Datos del documento</summary>@include('membership-bank.document-metadata', ['metadata'=>json_decode($import->document_metadata??'[]',true)])<p>SHA-256: {{$import->sha256}}</p></details></section>
<div class="statement-summary">
<div class="card"><strong>{{$import->processing_status==='legacy'?$total:$summary->movements}}</strong><small>Total movimientos</small></div>
<div class="card"><strong>{{$summary->credits}}</strong><small>Créditos · Bs {{number_format($summary->credit_total,2,'.',',')}}</small></div>
<div class="card"><strong>{{$summary->debits}}</strong><small>Débitos · Bs {{number_format($summary->debit_total,2,'.',',')}}</small></div>
<div class="card"><strong>{{$summary->new_count}}</strong><small>Movimientos nuevos</small></div>
<div class="card"><strong>{{$import->duplicate_count}}</strong><small>Duplicados</small></div>
<div class="card"><strong>{{$linked}}</strong><small>{{$linked}} conciliados</small></div>
<div class="card"><strong>{{$total-$linked}}</strong><small>{{$total-$linked}} pendientes · Sin identificar</small></div>
</div>
@if($overlaps)<p class="notice">El período coincide con {{$overlaps}} extracto(s) de la misma cuenta. Los movimientos repetidos se detectan sin duplicarlos.</p>@endif
@foreach(json_decode($import->incidents??'[]',true) as $incident)<p class="notice">{{$incident}}</p>@endforeach
<section class="card"><h2>Conciliación de créditos de Bs 100</h2>
<p>COM concilia automáticamente las coincidencias seguras. Los casos pendientes conservan sus evidencias para revisión excepcional.</p>
@forelse($candidates as $candidate)
<p>Movimiento #{{$candidate['movement']->id}} · {{$candidate['movement']->paid_date}} · Transacción {{$candidate['movement']->reference}}<br>Compra {{$candidate['payment']->reference}} · {{$candidate['payment']->user?->name??'Comprador pendiente'}}</p>
@empty<p>No hay coincidencias compatibles en esta página de compras pendientes. Los créditos sin identificar se conservan.</p>@endforelse
{{$pending->links()}}
<a class="button" href="/admin/conciliacion-membresias">Volver a Pagos y conciliación</a></section>
<h2>Movimientos</h2>
<div class="card statement-table"><table><thead><tr><th>Fecha</th><th>Descripción</th><th>Débito</th><th>Crédito</th><th>Saldo</th><th>Estado</th></tr></thead><tbody>
@foreach($entries as $entry) @php($row=json_decode($entry->data,true))
<tr><td data-label="Fecha">{{$row['paid_date']}}<small>{{$row['paid_time']}}</small></td>
<td data-label="Descripción"><div class="movement-description">{{$row['description']}}</div><small>Transacción {{$row['reference']}} · #{{$entry->movement_id}}</small><details><summary>Ver original</summary><pre>{{$entry->original_row}}</pre></details></td>
<td data-label="Débito">{{$row['debit']}}</td><td data-label="Crédito">{{$row['credit']}}</td><td data-label="Saldo">{{$row['balance']}}</td>
<td data-label="Estado">{{$entry->payment_reference?'Conciliado: '.$entry->payment_reference:($entry->disposition==='review_required'?'Retenido para revisión':'Sin identificar')}}@if($entry->disposition==='duplicate')<small>Duplicado de otra importación</small>@endif</td></tr>
@endforeach
@if($import->processing_status==='legacy') @foreach($legacy as $row)
<tr><td data-label="Fecha">{{$row->paid_date}} {{$row->paid_time}}</td><td data-label="Descripción">{{$row->description}}<small>{{$row->reference}}</small></td><td data-label="Débito">{{$row->debit}}</td><td data-label="Crédito">{{$row->credit??$row->amount}}</td><td data-label="Saldo">{{$row->balance??'No registrado'}}</td><td data-label="Estado">{{$row->payment_reference??'Sin identificar'}}</td></tr>
@endforeach @endif
</tbody></table></div>{{$entries->links()}}@if($import->processing_status==='legacy'){{$legacy->links()}}@endif
@endsection
