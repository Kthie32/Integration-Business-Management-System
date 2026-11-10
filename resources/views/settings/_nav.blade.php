@php
    $sections = array_filter([
        ['route' => 'settings.organization.edit', 'active' => 'settings.organization.*', 'label' => 'Organization', 'icon' => 'building', 'permission' => 'settings.organization'],
        ['route' => 'settings.finance.edit', 'active' => 'settings.finance.*', 'label' => 'Currency & defaults', 'icon' => 'cash-coin', 'permission' => 'settings.finance'],
        ['route' => 'settings.taxes.index', 'active' => 'settings.taxes.*', 'label' => 'Tax rates', 'icon' => 'percent', 'permission' => 'settings.finance'],
        ['route' => 'settings.numbering.index', 'active' => 'settings.numbering.*', 'label' => 'Document numbering', 'icon' => '123', 'permission' => 'settings.finance'],
        ['route' => 'settings.system.edit', 'active' => 'settings.system.*', 'label' => 'System', 'icon' => 'sliders', 'permission' => 'settings.system'],
    ], fn (array $section): bool => auth()->user()->can($section['permission']));
@endphp

<ul class="nav nav-underline flex-nowrap overflow-auto mb-4 border-bottom settings-nav">
    @foreach ($sections as $section)
        <li class="nav-item">
            <a href="{{ route($section['route']) }}" @class(['nav-link text-nowrap', 'active' => request()->routeIs($section['active'])])
                @if (request()->routeIs($section['active'])) aria-current="page" @endif>
                <i class="bi bi-{{ $section['icon'] }} me-1" aria-hidden="true"></i>{{ $section['label'] }}
            </a>
        </li>
    @endforeach
</ul>
