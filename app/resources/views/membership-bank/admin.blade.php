@extends('membership-bank.admin-layout')
@section('title','Pagos y conciliación')
@section('content')
<a href="/admin">← Administración COM</a>
<div class="toolbar"><h1>Pagos y conciliación</h1><a href="?vista=ajustes">Ajustes de pagos</a></div>
<p class="muted">COM concilia los pagos automáticamente al recibir el extracto y las evidencias necesarias.</p>
<div class="toolbar"><a class="button" href="/admin/extractos-bancarios#importar">Importar extracto bancario</a><a href="/admin/extractos-bancarios#historial">Historial de extractos</a></div>
<nav class="filters" aria-label="Filtrar pagos">
@foreach(['confirmados'=>'Pagos confirmados','pendientes'=>'Compras pendientes','sin-identificar'=>'Ingresos sin identificar','revision'=>'Casos que requieren revisión'] as $key=>$label)
<a class="card" href="?estado={{$key}}" @if($filter===$key) aria-current="page" @endif><strong>{{$summary[$key]}}</strong>{{$label}}</a>
@endforeach
</nav>
<form class="search" method="get"><input type="hidden" name="estado" value="{{$filter}}"><div><label for="purchase-search">Buscar comprador o referencia</label><input id="purchase-search" name="q" maxlength="120" value="{{$query}}" placeholder="Nombre o referencia de compra"></div><button class="secondary">Buscar</button></form>
<div class="toolbar"><h2>{{$listTitle}}</h2><a href="/admin/conciliacion-membresias">Ver todos los pagos</a></div>
<section class="card" aria-label="Lista de pagos">
<div class="payment-row payment-head" aria-hidden="true"><span>Comprador</span><span>Fecha</span><span>Importe</span><span>Estado</span><span>Detalle</span></div>
@forelse($payments as $p)
<div class="payment-row"><h3>{{$p->buyer_name ?? $p->user?->name ?? 'Comprador sin identificar'}}</h3><p>{{$p->paid_date?->format('d/m/Y') ?? $p->checkout_at?->timezone('America/La_Paz')->format('d/m/Y') ?? 'Sin fecha'}}</p><p><strong>Bs {{number_format((float)$p->amount,2)}}</strong></p><p class="status"><span class="badge">{{$statusLabels[$p->status]??$p->status}}</span></p><a class="detail-link" href="?pago={{$p->id}}" aria-label="Ver detalle de {{$p->buyer_name ?? $p->user?->name ?? 'compra'}}">Ver detalle</a></div>
@empty @if(!in_array($filter,['sin-identificar','revision'],true))<p>No hay pagos en esta vista.</p>@endif @endforelse
@foreach($incomes as $income)
<div class="payment-row"><h3>Comprador sin identificar</h3><p>{{\Carbon\CarbonImmutable::parse($income->paid_date)->format('d/m/Y')}}</p><p><strong>Bs {{number_format((float)$income->credit,2)}}</strong></p><p class="status"><span class="badge">{{$income->reconciliation_status==='unidentified'?'Sin identificar':'Requiere revisión'}}</span></p><a class="detail-link" href="?ingreso={{$income->id}}">Ver detalle</a></div>
@endforeach
@if(in_array($filter,['sin-identificar','revision'],true) && $payments->isEmpty() && $incomes->isEmpty())<p>No hay casos en esta vista.</p>@endif
{{$payments->links()}}{{$incomes->links()}}
</section>
@endsection
