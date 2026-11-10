<x-layouts::app title="Document numbering">
    <x-page-header title="Settings" subtitle="How each type of document is numbered, e.g. INV-2026-00042."
        :breadcrumbs="['Settings' => route('settings.index'), 'Document numbering' => null]" />

    @include('settings._nav')

    <div class="alert alert-info small d-flex gap-2">
        <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
        <div>
            Numbers are given out in order and never reused. You can move the next number forward (for example to continue
            from a previous system) but not backwards. If you change how often numbering restarts, check the next number too.
        </div>
    </div>

    <div class="d-flex flex-column gap-3">
        @foreach ($documentTypes as $row)
            @php
                $type = $row['type'];
                $bag = 'numbering_'.$type->value;
                $isOldRow = old('document_type') === $type->value;
                $field = fn (string $name, mixed $current) => $isOldRow ? old($name, $current) : $current;
                $errorsForRow = $errors->getBag($bag);
            @endphp
            <form method="POST" action="{{ route('settings.numbering.update', $type) }}" class="card" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" name="document_type" value="{{ $type->value }}">

                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-xl-3">
                            <div class="fw-semibold">{{ $type->label() }}</div>
                            <div class="small text-secondary">Next: <code>{{ $row['preview'] }}</code></div>
                        </div>
                        <div class="col-6 col-md-3 col-xl-2">
                            <label for="prefix-{{ $type->value }}" class="form-label small">Prefix</label>
                            <input type="text" name="prefix" id="prefix-{{ $type->value }}" maxlength="10" value="{{ $field('prefix', $row['config']['prefix']) }}"
                                @class(['form-control', 'is-invalid' => $errorsForRow->has('prefix')])>
                            @if ($errorsForRow->has('prefix'))<div class="invalid-feedback">{{ $errorsForRow->first('prefix') }}</div>@endif
                        </div>
                        <div class="col-6 col-md-2 col-xl-1">
                            <label for="padding-{{ $type->value }}" class="form-label small">Digits</label>
                            <input type="number" name="padding" id="padding-{{ $type->value }}" min="1" max="10" value="{{ $field('padding', $row['config']['padding']) }}"
                                @class(['form-control', 'is-invalid' => $errorsForRow->has('padding')])>
                            @if ($errorsForRow->has('padding'))<div class="invalid-feedback">{{ $errorsForRow->first('padding') }}</div>@endif
                        </div>
                        <div class="col-6 col-md-3 col-xl-2">
                            <label for="reset-{{ $type->value }}" class="form-label small">Restart numbering</label>
                            <select name="reset" id="reset-{{ $type->value }}" @class(['form-select', 'is-invalid' => $errorsForRow->has('reset')])>
                                @foreach ($resetOptions as $option)
                                    <option value="{{ $option->value }}" @selected($field('reset', $row['config']['reset']->value) === $option->value)>{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @if ($errorsForRow->has('reset'))<div class="invalid-feedback">{{ $errorsForRow->first('reset') }}</div>@endif
                        </div>
                        <div class="col-6 col-md-2 col-xl-2">
                            <label for="next-{{ $type->value }}" class="form-label small">Next number</label>
                            <input type="number" name="next_number" id="next-{{ $type->value }}" min="{{ $row['next_number'] }}" value="{{ $field('next_number', $row['next_number']) }}"
                                @class(['form-control', 'is-invalid' => $errorsForRow->has('next_number')])>
                            @if ($errorsForRow->has('next_number'))<div class="invalid-feedback">{{ $errorsForRow->first('next_number') }}</div>@endif
                        </div>
                        <div class="col-12 col-md-auto ms-xl-auto">
                            <button type="submit" class="btn btn-outline-primary w-100">Save</button>
                        </div>
                    </div>
                </div>
            </form>
        @endforeach
    </div>
</x-layouts::app>
