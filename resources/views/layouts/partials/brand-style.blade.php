@php
    $brandColor = (string) $settings->get('organization', 'brand_color', '#1e5aa8');
    $brandColor = preg_match('/^#[0-9a-fA-F]{6}$/', $brandColor) === 1 ? $brandColor : '#1e5aa8';
    [$red, $green, $blue] = sscanf($brandColor, '#%02x%02x%02x');
@endphp
{{-- The organization's brand colour from Settings, applied over the compiled Bootstrap theme. --}}
<style>
    :root {
        --bs-primary: {{ $brandColor }};
        --bs-primary-rgb: {{ $red }}, {{ $green }}, {{ $blue }};
        --bs-link-color: {{ $brandColor }};
        --bs-link-color-rgb: {{ $red }}, {{ $green }}, {{ $blue }};
        --bs-link-hover-color: color-mix(in srgb, {{ $brandColor }} 80%, #000);
        --bs-primary-bg-subtle: color-mix(in srgb, {{ $brandColor }} 14%, #fff);
        --bs-primary-border-subtle: color-mix(in srgb, {{ $brandColor }} 35%, #fff);
        --bs-primary-text-emphasis: color-mix(in srgb, {{ $brandColor }} 70%, #000);
    }
    .btn-primary {
        --bs-btn-bg: {{ $brandColor }};
        --bs-btn-border-color: {{ $brandColor }};
        --bs-btn-hover-bg: color-mix(in srgb, {{ $brandColor }} 85%, #000);
        --bs-btn-hover-border-color: color-mix(in srgb, {{ $brandColor }} 85%, #000);
        --bs-btn-active-bg: color-mix(in srgb, {{ $brandColor }} 75%, #000);
        --bs-btn-active-border-color: color-mix(in srgb, {{ $brandColor }} 75%, #000);
        --bs-btn-disabled-bg: {{ $brandColor }};
        --bs-btn-disabled-border-color: {{ $brandColor }};
    }
    .btn-outline-primary {
        --bs-btn-color: {{ $brandColor }};
        --bs-btn-border-color: {{ $brandColor }};
        --bs-btn-hover-bg: {{ $brandColor }};
        --bs-btn-hover-border-color: {{ $brandColor }};
        --bs-btn-active-bg: {{ $brandColor }};
        --bs-btn-active-border-color: {{ $brandColor }};
    }
    .form-check-input:checked { background-color: {{ $brandColor }}; border-color: {{ $brandColor }}; }
    .app-sidebar-link.active { box-shadow: inset 3px 0 0 {{ $brandColor }}; }
</style>
