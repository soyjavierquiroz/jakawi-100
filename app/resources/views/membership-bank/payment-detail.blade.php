@extends('membership-bank.admin-layout')
@section('title','Detalle de pago')
@section('content')
<a href="/admin/conciliacion-membresias">← Pagos y conciliación</a>
<h1>Detalle de pago</h1>
<section class="card"><h2>{{$p->buyer_name ?? $p->user?->name ?? 'Comprador sin identificar'}}</h2>
<p><strong>Bs {{number_format((float)$p->amount,2)}}</strong> · <span class="badge">{{$statusLabels[$p->status]??$p->status}}</span></p>
<p>{{$p->buyer_email ?? $p->user?->email}}</p><p>Compra {{$p->reference}}</p>
@if($p->club_reference)<p>Referencia CLUB: {{$p->club_reference}}</p>@endif
<p>Fecha: {{$p->paid_date?->format('d/m/Y') ?? $p->checkout_at?->timezone('America/La_Paz')->format('d/m/Y')}} {{$p->paid_time}}</p>
@if($p->payer_name)<p>Pagador: {{$p->payer_name}}</p>@endif
@if($p->review_note)<p>{{$p->review_note}}</p>@endif
<h3>Motivo y evidencias</h3><p>{{$decision?->reason ?? $review['reason'] ?? ($p->status==='approved' ? 'Pago confirmado y vinculado al movimiento bancario.' : 'La compra continúa pendiente de confirmación bancaria.')}}</p>
@if($decision)<details><summary>Evidencias de conciliación</summary><pre>{{json_encode(json_decode($decision->evidence,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)}}</pre></details>@endif
</section>
<section class="card"><h2>Movimiento bancario</h2>
@if($linkedMovement)
<p>Bs {{$linkedMovement->amount}} · {{$linkedMovement->paid_date}} {{$linkedMovement->paid_time}}</p><p>{{$linkedMovement->description}}</p><p>Referencia: {{$linkedMovement->reference}} · Movimiento #{{$linkedMovement->id}}</p><a href="/admin/extractos-bancarios/{{$linkedMovement->import_id}}">Ver extracto</a>
@else<p>Aún no hay un movimiento vinculado a esta compra.</p>
@foreach($review['candidates']??[] as $c)<details><summary>Candidato #{{$c->id}} · Bs {{$c->amount}} · {{$c->paid_date}}</summary><p>{{$c->paid_time}} · {{$c->reference}}</p><p>{{$c->description}}</p><p>{{$c->reconciliation_reason??''}}</p></details>@endforeach
@endif</section>
<section class="card"><h2>Comprobante y extracción IA</h2>
@if($p->needsReceipt())<p class="notice"><strong>Falta adjuntar comprobante</strong></p>@endif
@if($p->receipt_path)<a href="/admin/conciliacion-membresias/{{$p->id}}/comprobante">Comprobante recibido · Consultar original privado</a>@endif
@if($p->extraction_status)
<p><strong>Lectura del comprobante:</strong> {{['pending'=>'En cola','processing'=>'Procesando','completed'=>'Extraído','failed'=>'Falló; revisión manual disponible','unconfigured'=>'IA sin configurar; revisión manual disponible','manual'=>'Corregido por administrador'][$p->extraction_status]??'Pendiente'}}</p>
<small>Procesado: {{$p->extraction_processed_at?->timezone('America/La_Paz')->format('Y-m-d H:i')}} · Intentos: {{$p->extraction_attempts}}</small>@if($p->extraction_error)<small>Código: {{$p->extraction_error}}</small>@endif
@if($p->extraction_json)<details><summary>Datos y evidencias extraídos</summary><dl>@foreach(\App\Services\MembershipReceiptVision::FIELDS as $field)<dt><strong>{{['reference'=>'Referencia','amount'=>'Importe','currency'=>'Moneda','date'=>'Fecha','time'=>'Hora','origin_bank'=>'Banco de origen','recipient'=>'Destinatario','payer_name'=>'Pagador','origin_account'=>'Cuenta de origen (con máscara)'][$field]}}</strong></dt><dd>{{$p->extraction_json[$field]??'No legible / ausente'}} @if($p->extraction_json['evidence'][$field]??null)<small>Evidencia: {{$p->extraction_json['evidence'][$field]}}</small>@endif</dd>@endforeach</dl><small>Confianza: {{$p->extraction_json['confidence']??'Sin dato'}}</small></details>@endif
@endif
@if($p->status==='under_review')
<details><summary>Corregir datos del comprobante</summary><form method="post" action="/admin/conciliacion-membresias/{{$p->id}}/extraccion">@csrf
@foreach(['reference'=>'Referencia','amount'=>'Importe leído (punto decimal)','currency'=>'Moneda (BOB, USD...)','date'=>'Fecha (YYYY-MM-DD)','time'=>'Hora (HH:MM:SS)','origin_bank'=>'Banco de origen','recipient'=>'Destinatario','payer_name'=>'Pagador','origin_account'=>'Cuenta de origen (con máscara)'] as $field=>$label)
<label>{{$label}}</label><input name="{{$field}}" maxlength="255" value="{{$p->extraction_json[$field]??(['reference'=>$p->declared_reference,'date'=>$p->paid_date?->format('Y-m-d'),'time'=>$p->paid_time,'origin_bank'=>$p->origin_bank,'payer_name'=>$p->payer_name][$field]??'')}}">
@endforeach<button>Guardar corrección manual</button></form></details>
@if(in_array($p->extraction_status,['failed','unconfigured'],true))<form method="post" action="/admin/conciliacion-membresias/{{$p->id}}/extraccion/reintentar">@csrf<button>Reintentar extracción</button></form>@endif
@endif
@if(in_array($p->status,['under_review','PAYMENT_REPORTED'],true))<details><summary>Revisión excepcional: aprobar individualmente</summary><p>Confirma el ingreso bancario y su correspondencia inequívoca con esta compra. Puedes conciliar sin comprobante. La aprobación activa la membresía.</p><form method="post" action="/admin/conciliacion-membresias/aprobar">@csrf<input type="hidden" name="payment_id" value="{{$p->id}}"><label>Movimiento bancario (ID del listado)</label><input name="movement_id" type="number" min="1" required><button>Aprobar y activar</button></form></details><details><summary>Registrar revisión</summary><form method="post" action="/admin/conciliacion-membresias/{{$p->id}}/revision">@csrf<label>Decisión</label><select name="decision"><option value="no_match">Marcar sin coincidencia</option><option value="rejected">Rechazar y pedir corrección</option></select><label>Motivo (visible para el comprador)</label><textarea name="note" required maxlength="2000"></textarea><button>Guardar revisión</button></form></details>@endif

@if(!$p->receipt_path)<p>No hay comprobante adjunto.</p>@endif
</section>
@endsection
