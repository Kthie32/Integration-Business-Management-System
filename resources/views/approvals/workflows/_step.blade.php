@php
    $field = fn (string $name) => is_int($index) ? $errors->first("steps.{$index}.{$name}") : null;
    $type = $step['approver_type'] ?? '';
@endphp
<tr data-line>
    <td>
        <input type="text" name="steps[{{ $index }}][name]" value="{{ $step['name'] ?? '' }}" maxlength="100" placeholder="e.g. Finance"
            @class(['form-control', 'is-invalid' => $field('name')]) aria-label="Step name">
    </td>
    <td>
        <select name="steps[{{ $index }}][approver_type]" @class(['form-select', 'is-invalid' => $field('approver_type')]) aria-label="Approver">
            <option value="">Choose</option>
            @foreach ($approverTypes as $value => $label)
                <option value="{{ $value }}" @selected((string) $type === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($field('approver_type'))<div class="invalid-feedback">{{ $field('approver_type') }}</div>@endif
    </td>
    <td>
        <select name="steps[{{ $index }}][role_id]" @class(['form-select', 'is-invalid' => $field('role_id')]) aria-label="Role (for role steps)">
            <option value="">—</option>
            @foreach ($roles as $id => $name)
                <option value="{{ $id }}" @selected((string) ($step['role_id'] ?? '') === (string) $id)>{{ $name }}</option>
            @endforeach
        </select>
        @if ($field('role_id'))<div class="invalid-feedback">{{ $field('role_id') }}</div>@endif
    </td>
    <td>
        <select name="steps[{{ $index }}][user_id]" @class(['form-select', 'is-invalid' => $field('user_id')]) aria-label="User (for named-user steps)">
            <option value="">—</option>
            @foreach ($users as $id => $name)
                <option value="{{ $id }}" @selected((string) ($step['user_id'] ?? '') === (string) $id)>{{ $name }}</option>
            @endforeach
        </select>
        @if ($field('user_id'))<div class="invalid-feedback">{{ $field('user_id') }}</div>@endif
    </td>
    <td>
        <input type="number" name="steps[{{ $index }}][min_amount]" value="{{ $step['min_amount'] ?? '' }}" step="0.01" min="0" placeholder="Always"
            @class(['form-control', 'is-invalid' => $field('min_amount')]) aria-label="Only from this amount">
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-link text-danger" data-remove-line aria-label="Remove this step"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </td>
</tr>
