<section class="admin-nav-group">
<h2><button type="button" aria-expanded="true" aria-controls="admin-group-{{ $group['id'] }}"><svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="{{ $icons[$group['id']] }}"/></svg><span>{{ $group['label'] }}</span><svg class="admin-nav-chevron" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></button></h2>
<div id="admin-group-{{ $group['id'] }}">
@foreach($group['links'] as [$href, $label])<a href="{{ $href }}" @if($active === $href) aria-current="page" @endif>{{ $label }}</a>@endforeach
@if($group['id'] === 'growth')<a href="https://jakawi.club/admin/comisiones" target="_blank" rel="noopener noreferrer">Comisiones — CLUB ↗<small>Enlace externo · acceso propio de CLUB</small></a>@endif
</div>
</section>
