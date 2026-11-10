<div class="row">
    <div class="col-md-8">
        <x-form.input name="name" label="Name" :value="$supplier->name" maxlength="200" required autofocus />
    </div>
    <div class="col-md-4">
        <x-form.input name="tin" label="TIN" :value="$supplier->tin" maxlength="20" help="URA tax identification number." />
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <x-form.input name="contact_person" label="Contact person" :value="$supplier->contact_person" maxlength="150" />
    </div>
    <div class="col-md-4">
        <x-form.input name="phone" label="Phone" type="tel" :value="$supplier->phone" maxlength="30" />
    </div>
    <div class="col-md-4">
        <x-form.input name="email" label="Email" type="email" :value="$supplier->email" maxlength="255" />
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <x-form.input name="address" label="Address" :value="$supplier->address" maxlength="255" />
    </div>
    <div class="col-md-4">
        <x-form.input name="city" label="City / town" :value="$supplier->city" maxlength="100" />
    </div>
</div>

<h2 class="h6 mt-2 mb-3">Payment details</h2>
<div class="row">
    <div class="col-md-4">
        <x-form.input name="bank_name" label="Bank" :value="$supplier->bank_name" maxlength="100" />
    </div>
    <div class="col-md-4">
        <x-form.input name="bank_account" label="Account number" :value="$supplier->bank_account" maxlength="50" />
    </div>
    <div class="col-md-4">
        <x-form.input name="mobile_money_number" label="Mobile money number" type="tel" :value="$supplier->mobile_money_number" maxlength="30" />
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <x-form.input name="payment_terms_days" label="Payment terms (days)" type="number" min="0" max="365" :value="$supplier->payment_terms_days" required />
    </div>
</div>

<x-form.textarea name="notes" label="Notes" :value="$supplier->notes" maxlength="2000" />
<x-form.checkbox name="is_active" label="Active" :checked="$supplier->is_active ?? true" switch help="Inactive suppliers cannot be chosen on new goods receipts and expenses." />
