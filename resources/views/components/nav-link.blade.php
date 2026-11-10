@props(['route', 'icon', 'active' => null])

@php($isActive = request()->routeIs($active ?? $route))

<a href="{{ route($route) }}" @class(['app-sidebar-link', 'active' => $isActive]) @if ($isActive) aria-current="page" @endif>
    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    <span>{{ $slot }}</span>
</a>
