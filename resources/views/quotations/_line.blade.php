@php
    $error = fn (string $field): ?string => is_int($index) ? $errors->first("lines.{$index}.{$field}") : null;
@endphp
<tr data-line>
    <td>
        <select name="lines[{{ $index }}][item_id]" @class(['form-select form-select-sm mb-1', 'is-invalid' => $error('item_id')]) aria-label="Catalogue item" data-line-item>
            <option value="">Free-text line</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" data-price="{{ (string) $item->selling_price }}" data-unit="{{ $item->unit }}" data-name="{{ $item->name }}" data-tax="{{ $item->tax_rate_id }}"
                    @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->label() }}</option>
            @endforeach
        </select>
        @if ($error('item_id'))<div class="invalid-feedback d-block">{{ $error('item_id') }}</div>@endif
        <input type="text" name="lines[{{ $index }}][description]" value="{{ $line['description'] ?? '' }}" maxlength="500" placeholder="Description"
            @class(['form-control form-control-sm', 'is-invalid' => $error('description')]) aria-label="Description" data-line-description>
        @if ($error('description'))<div class="invalid-feedback">{{ $error('description') }}</div>@endif
    </td>
    <td>
        <input type="number" name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '' }}" step="0.001" min="0.001"
            @class(['form-control form-control-sm', 'is-invalid' => $error('quantity')]) aria-label="Quantity" data-line-quantity>
        @if ($error('quantity'))<div class="invalid-feedback">{{ $error('quantity') }}</div>@endif
    </td>
    <td>
        <input type="text" name="lines[{{ $index }}][unit]" value="{{ $line['unit'] ?? '' }}" maxlength="20"
            @class(['form-control form-control-sm', 'is-invalid' => $error('unit')]) aria-label="Unit" data-line-unit>
    </td>
    <td>
        <input type="number" name="lines[{{ $index }}][unit_price]" value="{{ $line['unit_price'] ?? '' }}" step="0.01" min="0"
            @class(['form-control form-control-sm', 'is-invalid' => $error('unit_price')]) aria-label="Unit price" data-line-price>
        @if ($error('unit_price'))<div class="invalid-feedback">{{ $error('unit_price') }}</div>@endif
    </td>
    <td>
        <input type="number" name="lines[{{ $index }}][discount_amount]" value="{{ $line['discount_amount'] ?? '' }}" step="0.01" min="0" placeholder="0"
            @class(['form-control form-control-sm', 'is-invalid' => $error('discount_amount')]) aria-label="Line discount amount" data-line-discount>
        @if ($error('discount_amount'))<div class="invalid-feedback">{{ $error('discount_amount') }}</div>@endif
    </td>
    <td>
        <select name="lines[{{ $index }}][tax_rate_id]" @class(['form-select form-select-sm', 'is-invalid' => $error('tax_rate_id')]) aria-label="Tax rate" data-line-tax>
            <option value="" data-rate="0">No tax</option>
            @foreach ($taxRates as $taxRate)
                <option value="{{ $taxRate->id }}" data-rate="{{ (string) $taxRate->rate }}" @selected((string) ($line['tax_rate_id'] ?? '') === (string) $taxRate->id)>{{ $taxRate->name }} ({{ $taxRate->displayRate() }})</option>
            @endforeach
        </select>
        @if ($error('tax_rate_id'))<div class="invalid-feedback">{{ $error('tax_rate_id') }}</div>@endif
    </td>
    <td class="text-end small pt-2" data-line-total>0</td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-link text-danger" data-remove-line aria-label="Remove this line"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </td>
</tr>
