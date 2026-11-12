@php
    $field = fn (string $name) => is_int($index) ? $errors->first("lines.{$index}.{$name}") : null;
    $itemError = $field('item_id');
    $descriptionError = $field('description');
    $quantityError = $field('quantity');
    $costError = $field('estimated_unit_cost');
@endphp
<tr data-line>
    <td>
        <select name="lines[{{ $index }}][item_id]" @class(['form-select', 'is-invalid' => $itemError]) aria-label="Catalogue item">
            <option value="">Not in catalogue</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->label() }}</option>
            @endforeach
        </select>
        @if ($itemError)<div class="invalid-feedback">{{ $itemError }}</div>@endif
    </td>
    <td>
        <input type="text" name="lines[{{ $index }}][description]" value="{{ $line['description'] ?? '' }}" maxlength="255" placeholder="Item name if blank"
            @class(['form-control', 'is-invalid' => $descriptionError]) aria-label="Description">
        @if ($descriptionError)<div class="invalid-feedback">{{ $descriptionError }}</div>@endif
    </td>
    <td>
        <input type="number" name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '' }}" step="0.001" min="0" data-line-quantity
            @class(['form-control', 'is-invalid' => $quantityError]) aria-label="Quantity">
        @if ($quantityError)<div class="invalid-feedback">{{ $quantityError }}</div>@endif
    </td>
    <td>
        <input type="number" name="lines[{{ $index }}][estimated_unit_cost]" value="{{ $line['estimated_unit_cost'] ?? '' }}" step="0.01" min="0" placeholder="Standard cost" data-line-cost
            @class(['form-control', 'is-invalid' => $costError]) aria-label="Estimated unit cost">
        @if ($costError)<div class="invalid-feedback">{{ $costError }}</div>@endif
    </td>
    <td class="text-end text-nowrap small" data-line-total>0</td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-link text-danger" data-remove-line aria-label="Remove this line"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </td>
</tr>
