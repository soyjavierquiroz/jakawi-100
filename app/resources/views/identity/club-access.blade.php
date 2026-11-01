<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Acceso a tus beneficios · JAKAWI</title>
@if($entry ?? false)
{{-- Remove the fragment before requesting even first-party assets. Never persist the raw ticket. --}}
<script>
window.clubAccessFragment = location.hash.slice(1);
history.replaceState(null, '', '/club/access');
window.addEventListener('hashchange', function () { location.reload(); });
</script>
@endif
@fonts
@vite('resources/css/app.css')
<style>
.club-access{margin:0;min-height:100svh;display:grid;place-items:center;padding:32px 20px;background:var(--background);color:var(--foreground);font-family:var(--font-discovery);text-align:center}
.club-access main{width:100%;max-width:400px}.club-access .brand{display:inline-flex;margin-bottom:32px}.club-access .brand img{width:122px;height:40px}.club-access h1{font-size:clamp(2rem,5vw,2.25rem);font-weight:800;line-height:1.08;letter-spacing:-.055em;margin:0 0 16px}.club-access p{color:var(--text-muted);font-size:16px;line-height:1.5;margin:0 0 28px}.club-access button,.club-access .return{font:inherit;font-size:14px;font-weight:800;cursor:pointer}.club-access .primary{display:flex;align-items:center;justify-content:center;width:100%;min-height:56px;border:0;border-radius:var(--radius-control);padding:16px 20px;background:var(--brand);color:var(--brand-foreground)}.club-access .primary:hover{background:color-mix(in srgb,var(--brand) 90%,transparent)}.club-access :is(button,a):focus-visible{outline:3px solid var(--brand);outline-offset:4px}.club-access .primary:disabled{cursor:wait;opacity:.7}.club-access .secondary,.club-access .return{display:inline-block;background:transparent;border:0;color:var(--text-secondary);padding:14px 8px;text-decoration:underline;text-underline-offset:4px;margin-top:12px}.club-access [hidden]{display:none!important}.club-access .loading{display:inline-block;width:24px;height:24px;border:3px solid var(--border);border-top-color:var(--brand);border-radius:50%;animation:turn .8s linear infinite;margin-bottom:20px}.club-access .signature{font-size:12px;margin:32px 0 0;color:var(--text-muted)}@keyframes turn{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.club-access .loading{animation:none}}
</style></head>
<body class="club-access"><main>
<a class="brand" href="https://jakawi.club" rel="noreferrer" aria-label="Volver a JAKAWI CLUB"><img src="/jakawi-logo.svg" alt="JAKAWI" width="122" height="40"></a>
@if(isset($error))
<h1>No pudimos abrir tus beneficios</h1><p role="alert">{{ $error }}</p>
<a class="return" href="https://jakawi.club" rel="noreferrer">Volver a CLUB</a>
@else
<div id="loading" @if(!$entry) hidden @endif role="status"><span class="loading" aria-hidden="true"></span><p>Preparando tus beneficios…</p></div>
<section id="confirmation" @if($entry) hidden @endif aria-labelledby="access-title">
<h1 id="access-title">{{ ($different ?? false) ? 'Estás usando otra cuenta JAKAWI.' : '¡Tu membresía está lista!' }}</h1>
<p id="access-description">{{ ($different ?? false) ? 'Al continuar, cerrarás la cuenta actual y entrarás con la de tu membresía.' : 'Ya puedes disfrutar tus beneficios.' }}</p>
<form method="post" action="/club/access/consume" id="access-form">@csrf
<input type="hidden" name="switch_account" value="yes" id="switch-account" @if(!($different ?? false)) disabled @endif>
<button class="primary" name="action" value="continue" id="enter">{{ ($different ?? false) ? 'Cambiar cuenta y entrar' : 'Entrar a mis beneficios' }}</button>
<button class="secondary" name="action" value="cancel" formnovalidate>Cancelar y volver a CLUB</button>
</form>
</section>
<section id="access-error" hidden><h1>No pudimos abrir tus beneficios</h1><p role="alert" id="error-message"></p><a class="return" href="https://jakawi.club" rel="noreferrer">Volver a CLUB</a></section>
<noscript><p>Activa JavaScript y vuelve a intentarlo desde CLUB.</p><a class="return" href="https://jakawi.club" rel="noreferrer">Volver a CLUB</a></noscript>
<script>
(function () {
    const form = document.getElementById('access-form');
    let submitting = false;
    form.addEventListener('submit', function (event) {
        if (submitting) { event.preventDefault(); return; }
        submitting = true;
        form.setAttribute('aria-busy', 'true');
        document.getElementById('enter').textContent = event.submitter?.value === 'cancel' ? 'Volviendo a CLUB…' : 'Abriendo tus beneficios…';
        form.querySelectorAll('button').forEach(button => { button.style.pointerEvents = 'none'; });
        // Keep the submitter enabled so its action reaches the server.
    });
    window.addEventListener('pageshow', function (event) { if (event.persisted) location.reload(); });
    @if($entry)
    async function prepare() {
        let ticket = window.clubAccessFragment;
        delete window.clubAccessFragment;
        if (!/^[a-f0-9]{64}$/.test(ticket || '')) {
            @if($staged ?? false)
            if (!ticket) { location.replace('/club/access/continue'); return; }
            @endif
            fail('Tu acceso expiró. Vuelve a intentarlo.'); return;
        }
        try {
            // Inspection only: opening the link never consumes or authenticates.
            const pending = fetch('/club/access/stage', { method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value },
                body: JSON.stringify({ ticket }) });
            ticket = null;
            const response = await pending;
            const state = await response.json();
            if (!response.ok) { fail(state.message || 'No pudimos confirmar tu acceso. Vuelve a CLUB para intentarlo de nuevo.'); return; }
            if (state.destination === '/beneficios') { location.replace('/beneficios'); return; }
            if (state.different) {
                document.getElementById('access-title').textContent = 'Estás usando otra cuenta JAKAWI.';
                document.getElementById('access-description').textContent = 'Al continuar, cerrarás la cuenta actual y entrarás con la de tu membresía.';
                document.getElementById('switch-account').disabled = false;
                document.getElementById('enter').textContent = 'Cambiar cuenta y entrar';
            }
            document.getElementById('loading').hidden = true;
            document.getElementById('confirmation').hidden = false;
        } catch (_) { fail('No pudimos abrir tu acceso. Vuelve a intentarlo desde CLUB.'); }
    }
    function fail(message) {
        document.getElementById('loading').hidden = true;
        document.getElementById('confirmation').hidden = true;
        document.getElementById('error-message').textContent = message;
        document.getElementById('access-error').hidden = false;
    }
    prepare();
    @endif
})();
</script>
@endif
<p class="signature">JAKAWI · Vive más tu ciudad.</p>
</main></body></html>
