@php
    $genderOptions = collect(App\Enums\Gender::cases())->mapWithKeys(fn ($gender) => [$gender->value => $gender->label()])->all();
    $typeOptions = collect(App\Enums\EmploymentType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all();
@endphp

<h2 class="h6 mb-3">Personal details</h2>
<div class="row">
    <div class="col-md-4">
        <x-form.input name="first_name" label="First name" :value="$employee->first_name" maxlength="100" required autofocus />
    </div>
    <div class="col-md-4">
        <x-form.input name="last_name" label="Last name" :value="$employee->last_name" maxlength="100" required />
    </div>
    <div class="col-md-4">
        <x-form.input name="other_names" label="Other names" :value="$employee->other_names" maxlength="100" />
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <x-form.select name="gender" label="Gender" :options="$genderOptions" :value="$employee->gender" placeholder="— Not stated —" />
    </div>
    <div class="col-md-4">
        <x-form.input name="date_of_birth" label="Date of birth" type="date" :value="$employee->date_of_birth?->toDateString()" />
    </div>
</div>

<h2 class="h6 mt-2 mb-3">Contact</h2>
<div class="row">
    <div class="col-md-4">
        <x-form.input name="phone" label="Phone" type="tel" :value="$employee->phone" maxlength="30" />
    </div>
    <div class="col-md-4">
        <x-form.input name="email" label="Email" type="email" :value="$employee->email" maxlength="255" />
    </div>
    <div class="col-md-4">
        <x-form.input name="address" label="Address" :value="$employee->address" maxlength="255" />
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <x-form.input name="next_of_kin_name" label="Next of kin" :value="$employee->next_of_kin_name" maxlength="150" />
    </div>
    <div class="col-md-6">
        <x-form.input name="next_of_kin_phone" label="Next of kin phone" type="tel" :value="$employee->next_of_kin_phone" maxlength="30" />
    </div>
</div>

<h2 class="h6 mt-2 mb-3">Employment</h2>
<div class="row">
    <div class="col-md-4">
        <x-form.select name="department_id" label="Department" :options="$departments" :value="$employee->department_id" placeholder="— Choose —" required />
    </div>
    <div class="col-md-4">
        <x-form.input name="job_title" label="Job title" :value="$employee->job_title" maxlength="150" required />
    </div>
    <div class="col-md-4">
        <x-form.select name="employment_type" label="Employment type" :options="$typeOptions" :value="$employee->employment_type" required />
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <x-form.input name="hire_date" label="Hire date" type="date" :value="$employee->hire_date?->toDateString()" required />
    </div>
    <div class="col-md-4">
        <x-form.input name="contract_end_date" label="Contract end date" type="date" :value="$employee->contract_end_date?->toDateString()" help="Required for contract staff." />
    </div>
    <div class="col-md-4">
        <x-form.select name="user_id" label="User account" :options="$users" :value="$employee->user?->id" placeholder="— No account —"
            help="Lets the employee sign in to raise requisitions and see their payslips." />
    </div>
</div>
<x-form.checkbox name="is_resident_for_tax" label="Resident for tax purposes" :checked="$employee->is_resident_for_tax ?? true" switch />

@if ($showSensitive)
    <h2 class="h6 mt-2 mb-3"><i class="bi bi-lock me-1" aria-hidden="true"></i>Identity and payment details</h2>
    <div class="row">
        <div class="col-md-4">
            <x-form.input name="nin" label="National ID (NIN)" :value="$employee->nin" maxlength="14" autocomplete="off" />
        </div>
        <div class="col-md-4">
            <x-form.input name="tin" label="TIN" :value="$employee->tin" maxlength="20" autocomplete="off" />
        </div>
        <div class="col-md-4">
            <x-form.input name="nssf_number" label="NSSF number" :value="$employee->nssf_number" maxlength="30" autocomplete="off" />
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <x-form.input name="bank_name" label="Bank" :value="$employee->bank_name" maxlength="100" autocomplete="off" />
        </div>
        <div class="col-md-4">
            <x-form.input name="bank_account" label="Account number" :value="$employee->bank_account" maxlength="50" autocomplete="off" />
        </div>
        <div class="col-md-4">
            <x-form.input name="mobile_money_number" label="Mobile money number" type="tel" :value="$employee->mobile_money_number" maxlength="30" autocomplete="off" />
        </div>
    </div>
@endif

@if ($employee->exists)
    <div class="border rounded p-3 bg-light mb-3">
        <p class="small text-secondary mb-2">Changes to department, job title or employment type are added to the employment history.</p>
        <div class="row">
            <div class="col-md-4">
                <x-form.input name="effective_date" label="Effective date of changes" type="date" :value="now()->toDateString()" class="mb-0" />
            </div>
            <div class="col-md-8">
                <x-form.input name="change_notes" label="Reason / notes" maxlength="255" />
            </div>
        </div>
    </div>
@endif
