@props(['name', 'label', 'checked' => false, 'help' => null, 'switch' => false])

{{-- Submits "1" when ticked and "0" when not, so unticking is saved too. --}}
@php($id = $attributes->get('id', $name))
@php($isChecked = (bool) old($name, $checked))

<div class="mb-3">
    <div @class(['form-check', 'form-switch' => $switch])>
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1" @checked($isChecked)
            {{ $attributes->except('id')->class(['form-check-input', 'is-invalid' => $errors->has($name)]) }}
            @if ($help) aria-describedby="{{ $id }}-help" @endif>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
        @error($name)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    @if ($help)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif
</div>
