@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'bag' => 'default', 'help' => null])

{{-- $options: value => label --}}
@php($id = $attributes->get('id', $name))
@php($error = $errors->getBag($bag)->first($name))
@php($selected = (string) old($name, $value instanceof BackedEnum ? $value->value : $value))

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if ($attributes->has('required'))<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>
    <select name="{{ $name }}" id="{{ $id }}"
        {{ $attributes->except('id')->class(['form-select', 'is-invalid' => $error]) }}
        @if ($error) aria-describedby="{{ $id }}-error" aria-invalid="true" @elseif ($help) aria-describedby="{{ $id }}-help" @endif>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($error)
        <div class="invalid-feedback" id="{{ $id }}-error">{{ $error }}</div>
    @elseif ($help)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif
</div>
