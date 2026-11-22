@php
$groups = json_decode(file_get_contents(resource_path('admin-navigation.json')), true, 512, JSON_THROW_ON_ERROR);
$path = '/'.trim(request()->path(), '/');
$matches = collect($groups)->flatMap(fn ($group) => array_column($group['links'], 0))->filter(fn ($href) => $path === $href || str_starts_with($path, $href.'/'))->sortByDesc(fn ($href) => strlen($href));
$active = $matches->first();
$icons = ['operation' => 'M3 10h18M5 10v11h14V10M3 10l2-7h14l2 7M9 21v-7h6v7', 'digital' => 'M5 7h14l2 14H3L5 7M9 7V5a3 3 0 0 1 6 0v2', 'growth' => 'M3 9h5l12-5v16L8 15H3V9M8 15l2 6', 'settings' => 'M12 3v3m0 12v3M3 12h3m12 0h3M6 6l2 2m8 8 2 2M6 18l2-2M16 8l2-2M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0'];
@endphp
<aside class="admin-sidebar">
<button type="button" class="admin-menu-trigger" aria-expanded="false" aria-controls="admin-drawer">Menú de administración</button>
<div id="admin-desktop-nav"><nav aria-label="Administración" class="admin-nav">
<a href="/admin" @if($path === '/admin') aria-current="page" @endif>Inicio del admin</a>
@foreach($groups as $group) @include('membership-bank.admin-navigation-group') @endforeach
</nav></div>
<dialog id="admin-drawer" class="admin-drawer" aria-labelledby="admin-drawer-title"><div class="admin-drawer-heading"><h2 id="admin-drawer-title">Administración</h2><button type="button" aria-label="Cerrar menú">Cerrar</button></div></dialog>
</aside>
<script>
{
 const root = document.querySelector('.admin-sidebar'), nav = root.querySelector('nav'), desktopNav = root.querySelector('#admin-desktop-nav'), dialog = root.querySelector('dialog'), trigger = root.querySelector('.admin-menu-trigger'), media = matchMedia('(min-width: 1024px)'), key = 'jakawi-admin-groups-v1';
 let closed = Array.from(nav.querySelectorAll('[aria-controls]'), button => button.getAttribute('aria-controls').replace('admin-group-', ''));
 try { const stored = JSON.parse(sessionStorage.getItem(key) || 'null'); if (Array.isArray(stored)) closed = stored; } catch {}
 nav.querySelectorAll('[aria-controls]').forEach(button => {
   const content = document.getElementById(button.getAttribute('aria-controls')), id = content.id.replace('admin-group-', '');
   const update = open => { button.setAttribute('aria-expanded', String(open)); content.hidden = !open; };
   update(!closed.includes(id) || !!content.querySelector('[aria-current="page"]'));
   button.addEventListener('click', () => { if (content.querySelector('[aria-current="page"]')) return; const open = button.getAttribute('aria-expanded') !== 'true'; update(open); closed = closed.filter(value => value !== id); if (!open) closed.push(id); try { sessionStorage.setItem(key, JSON.stringify(closed)); } catch {} });
 });
 const update = () => { dialog.close(); trigger.setAttribute('aria-expanded', 'false'); trigger.hidden = media.matches; (media.matches ? desktopNav : dialog).append(nav); };
 trigger.addEventListener('click', () => { dialog.showModal(); trigger.setAttribute('aria-expanded', 'true'); });
 dialog.querySelector('button').addEventListener('click', () => dialog.close());
 dialog.addEventListener('close', () => trigger.setAttribute('aria-expanded', 'false'));
 dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
 media.addEventListener('change', update); update();
}
</script>
