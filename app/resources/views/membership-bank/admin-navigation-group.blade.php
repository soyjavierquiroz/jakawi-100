<details class="{{ $nested ? 'admin-nav-secondary' : 'admin-nav-group' }}" @if(($group['expanded'] ?? false) || (!$nested && $group['label'] !== 'Configuración avanzada') || $containsCurrent($group)) open @endif>
<summary>{{ $group['label'] }}</summary>
@if(isset($group['description']))<p class="admin-nav-note">{{ $group['description'] }}</p>@endif
@foreach($group['links'] as [$href, $label])<a href="{{ $href }}" @if($current($href)) aria-current="page" @endif>{{ $label }}</a>@endforeach
@foreach($group['secondary'] ?? [] as $child)
@include('membership-bank.admin-navigation-group', ['group' => $child, 'nested' => true])
@endforeach
</details>
