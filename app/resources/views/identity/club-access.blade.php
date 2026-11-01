<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Acceso a tus beneficios · JAKAWI</title>
<style>body{font-family:system-ui;background:#f5f5f5;color:#182927;margin:0;padding:3rem 1rem}main{max-width:32rem;margin:auto;background:white;padding:2rem;border-radius:1rem}button,a{display:inline-block;padding:.8rem;margin:.5rem 0}button{cursor:pointer}button:first-of-type{background:#154f45;color:white;border:0;border-radius:.5rem}</style></head>
<body><main><h1>Accede a tus beneficios</h1>
@if(isset($error))
<p>{{ $error }}</p>
@elseif($entry)
<p>Continúa de forma segura desde CLUB. No necesitas otra contraseña ni volver a pagar.</p>
<form method="post" action="/club/access/stage" id="entry">@csrf<input type="hidden" name="ticket" id="ticket"><button id="start" disabled>Continuar</button></form>
<script>
(function(){const fragment=location.hash.slice(1);history.replaceState(null,'','/club/access');if(/^[a-f0-9]{64}$/.test(fragment)){document.getElementById('ticket').value=fragment;document.getElementById('start').disabled=false;}})();
</script>
<noscript>Activa JavaScript y solicita otro acceso desde CLUB.</noscript>
@else
@if($different)<p><strong>Hay otra cuenta COM abierta.</strong> Al continuar cerrarás esa sesión y entrarás con la cuenta autorizada para esta compra.</p>@else<p>Tu compra está autorizada. Continúa para abrir tu sesión COM y ver tus beneficios.</p>@endif
<form method="post" action="/club/access/consume">@csrf
@if($different)<label><input type="checkbox" name="switch_account" value="yes" required> Confirmo que quiero cambiar de cuenta.</label><br>@endif
<button name="action" value="continue">Entrar a mis beneficios</button>
<button name="action" value="cancel" formnovalidate>Cancelar y volver a CLUB</button></form>
@endif
<a href="https://jakawi.club" rel="noreferrer">Volver a CLUB</a></main></body></html>
