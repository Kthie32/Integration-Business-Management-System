@props(['name', 'label', 'type' => 'text', 'value' => null, 'bag' => 'default', 'help' => null])

@php($id = $attributes->get('id', $name))
@php($error = $errors->getBag($bag)->first($name))

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if ($attributes->has('required'))<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $id }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $error]) }}
        @if ($error) aria-describedby="{{ $id }}-error" aria-invalid="true" @elseif ($help) aria-describedby="{{ $id }}-help" @endif
    >
    @if ($error)
        <div class="invalid-feedback" id="{{ $id }}-error">{{ $error }}</div>
    @elseif ($help)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif
</div>
