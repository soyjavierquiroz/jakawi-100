@extends('membership-bank.admin-layout')
@section('title','Ajustes de pagos')
@section('content')
<a href="/admin/conciliacion-membresias">← Pagos y conciliación</a><h1>Ajustes de pagos</h1>
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

@endsection
