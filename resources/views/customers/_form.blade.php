@php($typeOptions = collect(App\Enums\CustomerType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all())

<div class="row">
    <div class="col-md-3">
        <x-form.select name="type" label="Type" :options="$typeOptions" :value="$customer->type" required />
    </div>
    <div class="col-md-6">
        <x-form.input name="name" label="Name" :value="$customer->name" maxlength="200" required autofocus />
    </div>
    <div class="col-md-3">
        <x-form.input name="tin" label="TIN" :value="$customer->tin" maxlength="20" help="URA tax identification number." />
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <x-form.input name="contact_person" label="Contact person" :value="$customer->contact_person" maxlength="150" />
    </div>
    <div class="col-md-4">
        <x-form.input name="phone" label="Phone" type="tel" :value="$customer->phone" maxlength="30" />
    </div>
    <div class="col-md-4">
        <x-form.input name="email" label="Email" type="email" :value="$customer->email" maxlength="255" />
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <x-form.input name="address" label="Address" :value="$customer->address" maxlength="255" />
    </div>
    <div class="col-md-4">
        <x-form.input name="city" label="City / town" :value="$customer->city" maxlength="100" />
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <x-form.input name="payment_terms_days" label="Payment terms (days)" type="number" min="0" max="365" :value="$customer->payment_terms_days" required help="Invoices fall due this many days after issue." />
    </div>
    <div class="col-md-4">
        <x-form.input name="credit_limit" label="Credit limit" inputmode="decimal" :value="$customer->credit_limit !== null ? rtrim(rtrim((string) $customer->credit_limit, '0'), '.') : null" help="Optional. Leave blank for no limit." />
    </div>
</div>

<x-form.textarea name="notes" label="Notes" :value="$customer->notes" maxlength="2000" />
<x-form.checkbox name="is_active" label="Active" :checked="$customer->is_active ?? true" switch help="Inactive customers cannot be chosen on new quotations and invoices." />
