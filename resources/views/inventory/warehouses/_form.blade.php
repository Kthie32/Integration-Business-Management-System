<div class="row">
    <div class="col-md-4">
        <x-form.input name="code" label="Code" :value="$warehouse->code" maxlength="20" required help="Letters, numbers and dashes, e.g. MAIN or KLA-01." />
    </div>
    <div class="col-md-8">
        <x-form.input name="name" label="Name" :value="$warehouse->name" maxlength="100" required />
    </div>
</div>
<x-form.input name="location" label="Location" :value="$warehouse->location" maxlength="255" />
<x-form.checkbox name="is_default" label="Default warehouse" :checked="(bool) $warehouse->is_default" help="Pre-selected on new receipts, issues and invoices. Exactly one warehouse is the default." switch />
<x-form.checkbox name="is_active" label="Active" :checked="$warehouse->is_active ?? true" help="A warehouse must be empty before it can be deactivated." switch />
