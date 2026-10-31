@extends('membership-bank.customer-layout')
@section('title','Activa tu membresía')
@section('content')
@if($metaBrowser)<script type="application/json" id="membership-meta-context">{!! json_encode($metaBrowser, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@vite('resources/js/membership-checkout.ts')@endif
@php($canPay = in_array($payment->status, ['awaiting_payment','rejected'],true))
<section class="payment-intro"><p class="discovery-eyebrow text-brand">MEMBRESÍA JAKAWI</p><h1>Activa tu membresía</h1><p class="payment-subtitle">Tu próximo paso para vivir más tu ciudad.</p><div class="payment-price">Bs 100 <span>BOB</span></div></section>
<div class="payment-grid"><div class="payment-summary">
<section class="payment-card" aria-labelledby="buyer-title"><p id="buyer-title" class="discovery-eyebrow text-muted-foreground">Esta membresía será para</p><h2 class="payment-buyer">{{trim($buyer->name) ?: 'Completa tu nombre'}}</h2><p class="payment-email">{{$buyer->email}}</p>@if(count(preg_split('/\s+/',trim($buyer->name),-1,PREG_SPLIT_NO_EMPTY)) < 2)<a class="payment-text-link" href="/settings/profile">Completar mi nombre en el perfil →</a>@endif<div class="payment-reference"><span>Referencia de compra</span><div><code id="purchase-reference">{{$payment->reference}}</code><button type="button" id="copy-reference" class="payment-copy" aria-label="Copiar referencia de compra">Copiar</button></div><span id="copy-status" role="status" aria-live="polite"></span></div></section>

</div><section class="payment-card payment-operation" aria-live="polite">
@if($payment->status==='approved')
<div class="payment-state payment-state-success"><span class="payment-state-icon" aria-hidden="true">✓</span><p class="discovery-eyebrow">Pago confirmado</p><h2>{{$active?'Tu membresía está activa':'Tu pago está conciliado'}}</h2><p>{{$active?'Ya puedes disfrutar de tu membresía. Encuentra tus beneficios en Mi JAKAWI.':'Consulta el estado actual de tu membresía en Mi JAKAWI.'}}</p><a href="/mi-jakawi" class="discovery-primary-action">Ir a Mi JAKAWI →</a></div>
@elseif($payment->status==='under_review')
<div class="payment-state"><span class="payment-state-icon" aria-hidden="true">◷</span><p class="discovery-eyebrow text-brand">Comprobante recibido</p><h2>Estamos verificando tu pago</h2><p>Recibimos tu comprobante. Estamos verificando tu pago. Te avisaremos cuando tu membresía esté activada.</p><p class="payment-small">Puedes volver a esta página para consultar tu estado.</p>@if($payment->review_note)<p class="payment-notice">{{$payment->review_note}}</p>@endif<a href="/mi-jakawi" class="discovery-secondary-action">Volver a Mi JAKAWI</a></div>
@else
@if($payment->status==='rejected')<div class="payment-notice payment-notice-error"><p class="discovery-eyebrow">Comprobante observado</p><h2>Corrige tu comprobante</h2><p>{{$payment->review_note ?: 'Necesitamos que revises los datos del pago y vuelvas a presentar tu comprobante.'}}</p><p class="payment-small">Si ya pagaste, corrige tu declaración; no necesitas pagar otra vez.</p></div>@else<p class="payment-chip">Pendiente de pago</p>@endif
@if(!$ready)<div class="payment-state"><h2>El pago por QR estará disponible pronto</h2><p>Aún no habilitamos los cobros. Vuelve cuando el QR bancario esté disponible.</p></div>
@else
@if($payment->status!=='rejected')
<div class="payment-recipient"><p class="discovery-eyebrow text-muted-foreground">Destinatario</p><p class="payment-recipient-name">{{$recipient}}</p><p class="payment-small">Confirma este destinatario en tu aplicación bancaria antes de efectuar el pago.</p><p class="discovery-eyebrow text-muted-foreground">Importe <strong class="text-foreground" style="text-transform:none">Bs 100</strong></p></div>
<figure class="payment-qr"><img src="/membresia/checkout/qr" alt="QR bancario original para pagar Bs 100 por tu membresía JAKAWI" width="340" height="340"><figcaption>Escanea el QR o descárgalo para usarlo en tu app bancaria.</figcaption></figure>
<a href="/membresia/checkout/qr/descargar" class="discovery-secondary-action payment-download" download>↓ Descargar QR</a>
@endif
<details class="payment-declaration" @if($errors->any()||$payment->status==='rejected') open @endif><summary class="discovery-primary-action">{{$payment->status==='rejected'?'Corregir comprobante':'Ya pagué'}} <span aria-hidden="true">→</span></summary><div class="payment-form-content"><h2>Envía tu comprobante</h2><p class="payment-small">La membresía sigue a nombre de {{$buyer->name}}. Guardaremos el comprobante de forma privada.</p>
<form method="post" action="/membresia/checkout/comprobante" enctype="multipart/form-data">@csrf
<div class="payment-field"><label for="receipt">Comprobante de pago</label><input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,application/pdf" required aria-describedby="receipt-help"><p id="receipt-help" class="payment-small">JPG, PNG o PDF · Máximo 10 MB</p></div>
<div class="payment-field"><label for="declared_reference">Número de transacción o depósito <span>(si aparece)</span></label><input id="declared_reference" name="declared_reference" maxlength="255" value="{{old('declared_reference',$payment->declared_reference)}}"></div>
<div class="payment-form-row"><div class="payment-field"><label for="paid_date">Fecha del pago</label><input id="paid_date" name="paid_date" type="date" max="{{now('America/La_Paz')->format('Y-m-d')}}" value="{{old('paid_date',$payment->paid_date?->format('Y-m-d'))}}" required></div><div class="payment-field"><label for="paid_time">Hora aproximada</label><input id="paid_time" name="paid_time" type="time" value="{{old('paid_time',substr($payment->paid_time??'',0,5))}}" required><p class="payment-small">Hora de Bolivia</p></div></div>
<div class="payment-field"><label for="origin_bank">Banco de origen <span>(opcional)</span></label><input id="origin_bank" name="origin_bank" maxlength="255" value="{{old('origin_bank',$payment->origin_bank)}}"></div>
<div class="payment-field"><label for="payer_name">Nombre de quien pagó <span>(opcional)</span></label><input id="payer_name" name="payer_name" maxlength="255" value="{{old('payer_name',$payment->payer_name)}}"><p class="payment-small">Solo si otra persona pagó por ti. El titular de la membresía no cambia.</p></div>
<button class="discovery-primary-action payment-submit">Enviar comprobante privado →</button></form></div></details>
@endif @endif
</section></div>
<script>
(() => {const button=document.getElementById('copy-reference');button.addEventListener('click',async()=>{const reference=document.getElementById('purchase-reference').textContent;const status=document.getElementById('copy-status');try{await navigator.clipboard.writeText(reference);status.textContent='Referencia copiada';}catch{const range=document.createRange();range.selectNodeContents(document.getElementById('purchase-reference'));const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);status.textContent='Selecciona y copia la referencia';}});})();
</script>
@endsection
