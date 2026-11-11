@php
    $itemError = is_int($index) ? $errors->first("lines.{$index}.item_id") : null;
    $changeError = is_int($index) ? $errors->first("lines.{$index}.quantity_change") : null;
    $costError = is_int($index) ? $errors->first("lines.{$index}.unit_cost") : null;
@endphp
<tr data-line>
    <td>
        <select name="lines[{{ $index }}][item_id]" @class(['form-select', 'is-invalid' => $itemError]) aria-label="Item">
            <option value="">Choose an item</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->label() }}</option>
            @endforeach
        </select>
        @if ($itemError)<div class="invalid-feedback">{{ $itemError }}</div>@endif
    </td>
    <td>
        <input type="number" name="lines[{{ $index }}][quantity_change]" value="{{ $line['quantity_change'] ?? '' }}" step="0.001" @class(['form-control', 'is-invalid' => $changeError]) aria-label="Change (+ to add, − to remove)">
        @if ($changeError)<div class="invalid-feedback">{{ $changeError }}</div>@endif
    </td>
    <td>
        <input type="number" name="lines[{{ $index }}][unit_cost]" value="{{ $line['unit_cost'] ?? '' }}" step="0.01" min="0" placeholder="Average cost" @class(['form-control', 'is-invalid' => $costError]) aria-label="Unit cost for increases">
        @if ($costError)<div class="invalid-feedback">{{ $costError }}</div>@endif
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-link text-danger" data-remove-line aria-label="Remove this line"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </td>
</tr>
