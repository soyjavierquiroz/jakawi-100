@extends('membership-bank.layout')
@section('title','Activa tu membresía JAKAWI')
@section('content')
@if($metaBrowser)<script type="application/json" id="membership-meta-context">{!! json_encode($metaBrowser, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@vite('resources/js/membership-checkout.ts')@endif
<div class="narrow"><span class="badge">Membresía JAKAWI</span><h1>Activa tu membresía JAKAWI.</h1><p class="price">Bs 100</p><p class="muted">Referencia de compra: {{$payment->reference}}</p>
<div class="card"><span class="badge">{{$payment->status}}</span>
@if($payment->status==='approved')
<h2>{{$active?'Tu membresía está activa.':'Pago conciliado.'}}</h2><p>{{$active?'Ya puedes usar tu membresía desde Mi JAKAWI.':'Consulta el estado actual de tu membresía en Mi JAKAWI.'}}</p><a class="button" href="/mi-jakawi">Ir a Mi JAKAWI</a>
@elseif($payment->status==='under_review')
<h2>Recibimos tu comprobante.</h2><p>Estamos verificando tu pago. Te avisaremos cuando tu membresía esté activada.</p><p class="muted">Puedes volver a esta página para consultar tu estado.</p>@if($payment->review_note)<p class="notice">{{$payment->review_note}}</p>@endif
@else
@if($payment->status==='rejected')<h2>Corrige tu declaración</h2><p class="notice">{{$payment->review_note}}</p>@endif
@if(!$ready)<h2>El pago por QR estará disponible pronto.</h2><p class="notice">Aún no habilitamos los cobros. Vuelve cuando el QR bancario esté disponible.</p>
@else
<h2>Paga con tu app bancaria</h2><ol><li>Escanea el QR y verifica el destinatario antes de confirmar.</li><li>Paga exactamente <strong>Bs 100</strong> y guarda el comprobante.</li><li>Pulsa “Ya pagué” y envíanos los datos.</li></ol><img src="/membresia/checkout/qr" alt="QR bancario para pagar la membresía JAKAWI" width="340" style="object-fit:contain"><p><a href="/membresia/checkout/qr" target="_blank" rel="noopener">Abrir QR en tamaño original</a></p>
<details @if($errors->any()||$payment->status==='rejected') open @endif><summary class="button">Ya pagué</summary><p>Tu declaración se revisará contra los ingresos bancarios antes de activar la membresía.</p>
<form method="post" action="/membresia/checkout/comprobante" enctype="multipart/form-data">@csrf
<label for="receipt">Comprobante JPG, PNG o PDF (máximo 10 MB)</label><input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,application/pdf" required>
<label for="declared_reference">Número de transacción o depósito, si aparece</label><input id="declared_reference" name="declared_reference" maxlength="255" value="{{old('declared_reference',$payment->declared_reference)}}">
<label for="paid_date">Fecha del pago</label><input id="paid_date" name="paid_date" type="date" max="{{now('America/La_Paz')->format('Y-m-d')}}" value="{{old('paid_date',$payment->paid_date?->format('Y-m-d'))}}" required>
<label for="paid_time">Hora aproximada (Bolivia)</label><input id="paid_time" name="paid_time" type="time" value="{{old('paid_time',substr($payment->paid_time??'',0,5))}}" required>
<label for="origin_bank">Banco de origen, si lo conoces</label><input id="origin_bank" name="origin_bank" maxlength="255" value="{{old('origin_bank',$payment->origin_bank)}}">
<button>Enviar comprobante privado</button></form></details>
@endif @endif
</div></div>
@endsection
