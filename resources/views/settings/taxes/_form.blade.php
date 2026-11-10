<div class="row">
    <div class="col-md-6">
        <x-form.input name="name" label="Name" :value="$taxRate->name" maxlength="100" required />
    </div>
    <div class="col-md-3">
        <x-form.input name="code" label="Code" :value="$taxRate->code" maxlength="20" required help="Short unique code, e.g. VAT." />
    </div>
    <div class="col-md-3">
        <x-form.input name="rate" label="Rate (%)" inputmode="decimal" :value="$taxRate->rate !== null ? rtrim(rtrim((string) $taxRate->rate, '0'), '.') : null" required />
    </div>
</div>

<x-form.checkbox name="is_active" label="Active" :checked="$taxRate->is_active ?? true" switch />
<x-form.checkbox name="is_default" label="Default rate for new items and document lines" :checked="$taxRate->is_default" switch />
