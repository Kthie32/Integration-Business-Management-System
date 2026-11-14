<div class="row">
    <div class="col-sm-4">
        <x-form.input name="code" label="Code" maxlength="20" :value="$payComponent->code" required />
    </div>
    <div class="col-sm-8">
        <x-form.input name="name" label="Name" maxlength="100" :value="$payComponent->name" required />
    </div>
    <div class="col-sm-6">
        <x-form.select name="kind" label="Type" :options="$kinds" :value="$payComponent->kind" required />
    </div>
    <div class="col-sm-6">
        <x-form.select name="calculation" label="Calculation" :options="$calculations" :value="$payComponent->calculation" required
            help="Allowances cannot be a % of gross pay." />
    </div>
    <div class="col-sm-6">
        <x-form.input name="default_value" label="Default amount or %" type="number" step="0.0001" min="0" :value="(string) $payComponent->default_value" required
            help="Suggested when assigning it to a salary." />
    </div>
</div>
<x-form.checkbox name="is_taxable" label="Taxable (allowance) / taken off before PAYE (deduction)" :checked="$payComponent->is_taxable" />
<x-form.checkbox name="is_nssf_applicable" label="Counts for NSSF (allowance) / taken off before NSSF (deduction)" :checked="$payComponent->is_nssf_applicable" />
<x-form.checkbox name="is_active" label="Active" :checked="$payComponent->is_active" switch />
<x-form.input name="description" label="Description" maxlength="255" :value="$payComponent->description" />
