@props(['status' => null, 'label' => null, 'color' => 'secondary'])

{{-- Pass a status enum implementing App\Enums\Contracts\StatusEnum, or a plain label and colour. --}}
@php
    $text = $status instanceof App\Enums\Contracts\StatusEnum ? $status->label() : ($label ?? (string) $status);
    $tone = $status instanceof App\Enums\Contracts\StatusEnum ? $status->color() : $color;
@endphp

<span {{ $attributes->class(["badge rounded-pill bg-{$tone}-subtle text-{$tone}-emphasis border border-{$tone}-subtle"]) }}>{{ $text }}</span>
