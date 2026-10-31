@extends('membership-bank.layout')
@section('title','Conciliación de membresías')
@section('content')
<a href="/admin">← Administración COM</a><h1>Conciliación de membresías</h1><p>Ingresos de Bs 100. Cada aprobación activa la membresía y utiliza un único movimiento bancario.</p>
<section class="card" id="configuracion-qr" aria-labelledby="qr-heading">
<h2 id="qr-heading">Configuración del QR bancario</h2>
<p>QR bancario real para la membresía de <strong>Bs 100</strong>. Comprueba el destinatario y que el QR se pueda escanear antes de guardar.</p>
@if($qrConfigured)
<p class="badge">QR configurado · Pago habilitado</p><div><img src="/admin/conciliacion-membresias/qr" alt="QR bancario configurado para la membresía de Bs 100" width="400" style="object-fit:contain"></div>
<a href="/admin/conciliacion-membresias/qr" target="_blank" rel="noopener">Abrir imagen original</a> · <a href="/membresia/checkout">Ver checkout de Bs 100</a>
@else
<p class="notice">No hay un QR válido disponible. El checkout mantiene los cobros deshabilitados.</p>
@endif
<form method="post" action="/admin/conciliacion-membresias/qr" enctype="multipart/form-data">@csrf
<label for="recipient_name">Destinatario bancario</label><input id="recipient_name" name="recipient_name" value="{{old('recipient_name',$recipient)}}" maxlength="255" required>
<label for="qr_image">{{$qrConfigured ? 'Reemplazar imagen PNG/JPG' : 'Subir imagen PNG/JPG'}}</label>
<input id="qr_image" name="qr_image" type="file" accept="image/png,image/jpeg" aria-describedby="qr-help">
<p class="muted" id="qr-help">Se conserva el archivo original en MinIO, sin recortes ni recompresión. De 128 a 4096 píxeles por lado; máximo 10 MB. La nueva imagen se aplica al guardar. Deja el archivo vacío para cambiar solo el destinatario.</p>
<div id="qr-local-preview" hidden><p><strong>Vista previa de la nueva imagen</strong></p><img id="qr-local-image" alt="Vista previa del QR seleccionado" width="400" style="object-fit:contain"></div>
<button>Guardar cambios</button></form>
<script>
(() => {
    const input = document.getElementById('qr_image');
    const preview = document.getElementById('qr-local-preview');
    const image = document.getElementById('qr-local-image');
    let objectUrl;
    input.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = input.files[0];
        preview.hidden = !file || !['image/png', 'image/jpeg'].includes(file.type);
        image.removeAttribute('src');
        if (!preview.hidden) { objectUrl = URL.createObjectURL(file); image.src = objectUrl; }
    });
})();
</script></section>
<div class="card"><h2>Importar extracto</h2><p>Exporta desde el banco o convierte desde Excel a CSV UTF-8 normalizado. Fechas y horas en Bolivia. Usa siempre el mismo identificador de cuenta para detectar movimientos repetidos.</p><details><summary>Formato CSV</summary><pre style="overflow:auto">date,time,reference,description,amount,direction
2026-10-30,14:30,OPERACION,Abono,100.00,credit</pre><p>Fecha YYYY-MM-DD; hora HH:MM o HH:MM:SS; punto decimal sin separadores de miles. Hora y referencia pueden quedar vacías. Direction: credit o debit. Solo se importan créditos. Máximo 10.000 filas / 10 MB. Una referencia repetida en la misma cuenta, fecha e importe se conserva una vez; los ingresos indistinguibles sin referencia se consolidan por seguridad.</p></details>
<form method="post" action="/admin/conciliacion-membresias/importar" enctype="multipart/form-data">@csrf<label for="account_key">Identificador estable de cuenta (sin número bancario)</label><input id="account_key" name="account_key" placeholder="CUENTA-PRINCIPAL" pattern="[A-Z0-9_-]{1,100}" required><label for="statement">Extracto CSV</label><input id="statement" name="statement" type="file" accept=".csv,text/csv" required><button>Importar ingresos</button></form></div>
<form id="batch" method="post" action="/admin/conciliacion-membresias/lote">@csrf<button>Aprobar seleccionadas fuertes y únicas</button></form>
<p class="muted">Importar o clasificar nunca aprueba pagos. Las coincidencias por fecha, hora o importe requieren revisión manual.</p>
<div class="card table"><table><thead><tr><th>Lote</th><th>Comprador / compra</th><th>Referencia leída / corregida</th><th>Fecha / hora declarada</th><th>Movimiento candidato / importe</th><th>Coincidencia / estado</th><th>Decisiones</th></tr></thead><tbody>
@foreach($payments as $p) @php($m=$matches[$p->id]??null)
<tr><td>@if(($m['classification']??null)==='strong_unique')<input aria-label="Seleccionar compra {{$p->reference}}" form="batch" type="checkbox" name="payments[]" value="{{$p->id}}">@endif</td><td>{{$p->buyer_name ?? $p->user?->name}}<small>{{$p->buyer_email ?? $p->user?->email}}</small><small>{{$p->reference}}</small><small>Origen: {{$p->origin}} @if($p->club_reference) · CLUB {{$p->club_reference}} @endif</small>@if($p->buyer_phone)<small>Contacto declarado: {{$p->buyer_phone}}</small>@endif @if($p->identity_status)<small>Vínculo: {{$p->identity_status}}</small>@endif<small>Inicio: {{$p->checkout_at?->format('Y-m-d H:i')}} · Bs {{$p->amount}} BOB</small><small>Identidad COM: {{$p->identity_status === 'linked' || $p->origin === 'COM' ? $p->user?->global_subject_id : 'Pendiente de vínculo'}}</small>@if($p->user?->profile?->whatsapp)<small>Contacto: {{$p->user->profile->whatsapp}}</small>@endif @if($p->payer_name)<small>Pagó por el titular: {{$p->payer_name}}</small>@endif</td><td>{{$p->declared_reference??'No declarada'}}</td><td>{{$p->paid_date?->format('Y-m-d')}} {{$p->paid_time}}<small>Bolivia</small></td><td>@forelse($m['candidates']??[] as $c)<p>#{{$c->id}} · {{$c->reference??'Sin referencia'}}<small>{{$c->paid_date}} {{$c->paid_time}}</small><small>{{$c->description}}</small><strong>Bs {{$c->amount}}</strong></p>@empty Sin candidato @endforelse</td><td>{{$p->status}}<small>{{['strong_unique'=>'A · Fuerte y única','probable'=>'B · Probable; revisión individual','ambiguous'=>'C · Ambigua; revisión individual','no_match'=>'D · Sin coincidencia'][$m['classification']??'']??''}}</small><small>{{$p->review_note}}</small></td><td>
@if($p->receipt_path)<a href="/admin/conciliacion-membresias/{{$p->id}}/comprobante">Consultar comprobante privado</a>@endif
@if($p->extraction_status)
<p><strong>Lectura del comprobante:</strong> {{['pending'=>'En cola','processing'=>'Procesando','completed'=>'Extraído','failed'=>'Falló; revisión manual disponible','unconfigured'=>'IA sin configurar; revisión manual disponible','manual'=>'Corregido por administrador'][$p->extraction_status]??'Pendiente'}}</p>
<small>Procesado: {{$p->extraction_processed_at?->timezone('America/La_Paz')->format('Y-m-d H:i')}} · Intentos: {{$p->extraction_attempts}}</small>@if($p->extraction_error)<small>Código: {{$p->extraction_error}}</small>@endif
@if($p->extraction_json)<details><summary>Datos y evidencias extraídos</summary><dl>@foreach(\App\Services\MembershipReceiptVision::FIELDS as $field)<dt><strong>{{['reference'=>'Referencia','amount'=>'Importe','currency'=>'Moneda','date'=>'Fecha','time'=>'Hora','origin_bank'=>'Banco de origen','recipient'=>'Destinatario','payer_name'=>'Pagador'][$field]}}</strong></dt><dd>{{$p->extraction_json[$field]??'No legible / ausente'}} @if($p->extraction_json['evidence'][$field]??null)<small>Evidencia: {{$p->extraction_json['evidence'][$field]}}</small>@endif</dd>@endforeach</dl><small>Confianza: {{$p->extraction_json['confidence']??'Sin dato'}}</small></details>@endif
@endif
@if($p->status==='under_review')
<details><summary>Corregir datos del comprobante</summary><form method="post" action="/admin/conciliacion-membresias/{{$p->id}}/extraccion">@csrf
@foreach(['reference'=>'Referencia','amount'=>'Importe leído (punto decimal)','currency'=>'Moneda (BOB, USD...)','date'=>'Fecha (YYYY-MM-DD)','time'=>'Hora (HH:MM:SS)','origin_bank'=>'Banco de origen','recipient'=>'Destinatario','payer_name'=>'Pagador'] as $field=>$label)
<label>{{$label}}</label><input name="{{$field}}" maxlength="255" value="{{$p->extraction_json[$field]??(['reference'=>$p->declared_reference,'date'=>$p->paid_date?->format('Y-m-d'),'time'=>$p->paid_time,'origin_bank'=>$p->origin_bank,'payer_name'=>$p->payer_name][$field]??'')}}">
@endforeach<button>Guardar corrección manual</button></form></details>
@if(in_array($p->extraction_status,['failed','unconfigured'],true))<form method="post" action="/admin/conciliacion-membresias/{{$p->id}}/extraccion/reintentar">@csrf<button>Reintentar extracción</button></form>@endif
@endif
@if($p->status==='under_review')<details><summary>Aprobar individualmente</summary><p>Revisa el comprobante y el ingreso. La aprobación activa la membresía.</p><form method="post" action="/admin/conciliacion-membresias/aprobar">@csrf<input type="hidden" name="payment_id" value="{{$p->id}}"><label>Movimiento bancario (ID del listado)</label><input name="movement_id" type="number" min="1" required><button>Aprobar y activar</button></form></details><details><summary>Registrar revisión</summary><form method="post" action="/admin/conciliacion-membresias/{{$p->id}}/revision">@csrf<label>Decisión</label><select name="decision"><option value="no_match">Marcar sin coincidencia</option><option value="rejected">Rechazar y pedir corrección</option></select><label>Motivo (visible para el comprador)</label><textarea name="note" required maxlength="2000"></textarea><button>Guardar revisión</button></form></details>@endif
</td></tr>@endforeach</tbody></table>{{$payments->links()}}</div>
<div class="card table"><h2>Ingresos disponibles</h2><p>Para revisión individual. Los movimientos usados desaparecen de esta lista.</p><table><thead><tr><th>ID</th><th>Fecha / hora</th><th>Referencia</th><th>Descripción</th><th>Importe</th></tr></thead><tbody>@foreach($movements as $c)<tr><td>{{$c->id}}</td><td>{{$c->paid_date}} {{$c->paid_time}}</td><td>{{$c->reference}}</td><td>{{$c->description}}</td><td>Bs {{$c->amount}}</td></tr>@endforeach</tbody></table>{{$movements->links()}}</div>
@endsection
