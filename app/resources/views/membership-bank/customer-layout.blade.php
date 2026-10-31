<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>@yield('title') · JAKAWI</title><link rel="icon" href="/favicon.svg" type="image/svg+xml">@fonts @vite('resources/css/app.css')</head>
<body class="membership-checkout font-discovery antialiased bg-background text-foreground">
<header class="payment-header"><nav aria-label="Navegación de pago"><a href="/explorar" aria-label="Explorar JAKAWI"><img src="/jakawi-logo.svg" alt="JAKAWI" width="122" height="40"></a><a href="/mi-jakawi" class="discovery-secondary-action">Mi JAKAWI <span aria-hidden="true">↗</span></a></nav></header>
<main id="contenido" class="payment-main">@if(session('status'))<p class="payment-notice" role="status">{{session('status')}}</p>@endif @if($errors->any())<div class="payment-notice payment-notice-error" role="alert"><strong>Revisa los datos de tu comprobante</strong>@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif @yield('content')</main>
<footer class="payment-footer">JAKAWI · Vive más tu ciudad.<a href="/mi-jakawi">Volver a Mi JAKAWI</a></footer>
</body></html>
