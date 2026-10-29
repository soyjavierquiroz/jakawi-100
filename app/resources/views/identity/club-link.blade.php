<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>Vincular CLUB · JAKAWI</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#faf9f6;color:#192725;font-family:system-ui,sans-serif;line-height:1.55;min-height:100vh;display:grid;place-items:center;padding:24px}main{width:100%;max-width:480px;padding:36px;background:white;border:1px solid #e1e7e2;border-radius:20px}strong.brand{letter-spacing:.18em;color:#367561}h1{font-size:26px;line-height:1.2;margin-top:28px}p{color:#52625c;overflow-wrap:anywhere}button,a{font:inherit}button{display:block;width:100%;padding:14px 18px;background:#285c48;color:white;border:0;border-radius:10px;cursor:pointer;margin-top:24px}a{color:#285c48}.account{padding:12px;background:#f4f6f2;border-radius:8px;color:#192725}.fine{font-size:14px}
    </style>
</head>
<body><main>
    <strong class="brand">JAKAWI</strong>
    @if($linked)
        <h1>Tu cuenta está vinculada</h1>
        <p>Autorizaste la vinculación con JAKAWI.CLUB. Puedes volver a CLUB para continuar.</p>
    @elseif(!$fresh)
        <h1>Confirma tu identidad en COM</h1>
        <p>Para vincular tu cuenta con JAKAWI.CLUB, vuelve a iniciar sesión en JAKAWI.COM.</p>
        <form method="POST" action="{{ route('identity.club.reauthenticate', ['handoff' => $handoff]) }}">@csrf
            <button type="submit">Volver a iniciar sesión en COM</button>
        </form>
    @else
        <h1>Vincular con JAKAWI.CLUB</h1>
        <p>Estás autenticado en JAKAWI.COM con:</p>
        <p class="account">{{ $email }}</p>
        <p>Al autorizar, ambas aplicaciones reconocerán tu identidad global. Cada aplicación conservará sus datos y su sesión.</p>
        <form method="POST" action="{{ route('identity.club.authorize', ['handoff' => $handoff]) }}">@csrf
            <input type="hidden" name="authorize_link" value="1">
            <button type="submit">Autorizar vinculación con CLUB</button>
        </form>
        <p class="fine">Este vínculo conserva tus permisos y membresías actuales.</p>
    @endif
    <p class="fine"><a href="https://jakawi.club">Volver a JAKAWI.CLUB</a></p>
</main></body></html>
