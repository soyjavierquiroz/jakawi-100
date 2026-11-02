@php
$groups = json_decode(file_get_contents(resource_path('admin-navigation.json')), true, 512, JSON_THROW_ON_ERROR);
$current = fn ($href) => request()->is(ltrim($href, '/')) || request()->is(ltrim($href, '/').'/*');
$containsCurrent = function ($group) use (&$containsCurrent, $current) {
    foreach ($group['links'] as [$href]) { if ($current($href)) return true; }
    foreach ($group['secondary'] ?? [] as $child) { if ($containsCurrent($child)) return true; }
    return false;
};
@endphp
<aside class="admin-sidebar"><details class="admin-menu" open><summary>Menú de administración</summary><nav aria-label="Administración" class="admin-nav">
<a href="/admin" class="admin-nav-home">Inicio del admin</a>
@foreach($groups as $group) @include('membership-bank.admin-navigation-group', ['group' => $group, 'nested' => false]) @endforeach
<section class="admin-nav-group" aria-label="Comisiones"><h2 style="font-size:13px;padding:8px 12px">Comisiones</h2><a class="admin-club-link" href="https://jakawi.club/admin/comisiones" target="_blank" rel="noopener noreferrer">Administración de comisiones — CLUB ↗<small>Enlace externo · acceso propio de CLUB</small></a></section>
</nav></details></aside>
<script>{const desktop = window.matchMedia('(min-width: 1024px)'); const update = () => { document.querySelector('.admin-menu').open = desktop.matches; }; update(); desktop.addEventListener('change', update);}</script>
