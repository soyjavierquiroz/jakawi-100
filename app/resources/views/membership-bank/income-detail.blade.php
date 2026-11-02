@extends('membership-bank.admin-layout')
@section('title','Detalle de ingreso')
@section('content')
<a href="/admin/conciliacion-membresias?estado=sin-identificar">← Pagos y conciliación</a><h1>Detalle de ingreso</h1>
<section class="card"><h2>Comprador sin identificar</h2><p><strong>Bs {{number_format((float)$income->credit,2)}}</strong> · {{$income->paid_date}} {{$income->paid_time}}</p><span class="badge admin-status-warning">{{$income->reconciliation_status==='unidentified'?'Sin identificar':'Requiere revisión'}}</span><h3>Movimiento bancario</h3><p>{{$income->description}}</p><p>Referencia: {{$income->reference}} · Movimiento #{{$income->id}}</p><a href="/admin/extractos-bancarios/{{$income->import_id}}">Ver extracto bancario</a><h3>Motivo y evidencias</h3><p>{{$income->reason}}</p><details><summary>Ver evidencias</summary><pre>{{json_encode(json_decode($income->evidence,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)}}</pre></details><h3>Comprobante y extracción IA</h3><p>Aún no hay una compra vinculada a este ingreso.</p><a href="/admin/conciliacion-membresias?estado=revision">Consultar compras que requieren revisión</a></section>
@endsection
