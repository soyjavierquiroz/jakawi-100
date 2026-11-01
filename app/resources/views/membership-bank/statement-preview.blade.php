@extends('membership-bank.layout')
@section('title','Validar extracto bancario')
@section('content')
<a href="/admin/extractos-bancarios">← Historial de extractos</a><h1>Previsualizar y validar movimientos</h1>
<p>{{$p->original_name}} · {{$p->bank}} · {{$p->account_key}} · {{$p->period_start}} — {{$p->period_end}}</p><p>SHA-256: {{$p->sha256}}</p>
@include('membership-bank.document-metadata', ['metadata'=>json_decode($p->document_metadata??'[]',true)])
@if($existing)<p>Archivo idéntico ya registrado: <a href="/admin/extractos-bancarios/{{$existing}}">ver historial #{{$existing}}</a></p>@endif
@foreach($incidents as $incident)<p class="notice">{{$incident}}</p>@endforeach
<p>{{count($rows)}} movimientos extraídos. Aún no incorporados a conciliación. Revise el período, la cuenta y los valores contra el original.</p>
<div class="card table"><table><thead><tr><th>Fecha / hora</th><th>Referencia / descripción</th><th>Débito</th><th>Crédito</th><th>Importe</th><th>Saldo</th><th>Original / incidencia</th></tr></thead><tbody>
@foreach($rows as $row)<tr><td>{{$row['paid_date']}} {{$row['paid_time']}}</td><td>{{$row['reference']}}<small>{{$row['description']}}</small></td><td>{{$row['debit']}}</td><td>{{$row['credit']}}</td><td>{{$row['amount']}}</td><td>{{$row['balance']}}</td><td><details><summary>Fila original</summary><pre>{{$row['original_row']}}</pre></details>@if($row['weak_identity'] || $row['occurrence']>1)<small>Identidad insuficiente o repetición: quedará retenido para revisión.</small>@endif</td></tr>@endforeach
</tbody></table></div>
@if($rows && !$incidents)<form method="post" action="/admin/extractos-bancarios/preview/{{$p->id}}/confirmar">@csrf<label><input type="checkbox" name="validated" value="1" required> Validé la cuenta, el período y los movimientos contra el extracto original.</label><button>Incorporar al historial</button></form>@endif
@endsection
