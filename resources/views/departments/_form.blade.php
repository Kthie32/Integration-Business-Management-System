<div class="row">
    <div class="col-md-3">
        <x-form.input name="code" label="Code" :value="$department->code" maxlength="20" required autofocus help="Short code, e.g. FIN." />
    </div>
    <div class="col-md-9">
        <x-form.input name="name" label="Name" :value="$department->name" maxlength="150" required />
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <x-form.select name="parent_id" label="Part of" :options="$parents" :value="$department->parent_id" placeholder="— None (top level) —" />
    </div>
    <div class="col-md-6">
        <x-form.select name="head_employee_id" label="Head of department" :options="$heads" :value="$department->head_employee_id" placeholder="— Not assigned —"
            help="The head approves requisitions and sees this department's employees. Link their employee record to a user account so they can sign in." />
    </div>
</div>

<x-form.input name="description" label="Description" :value="$department->description" maxlength="255" />
<x-form.checkbox name="is_active" label="Active" :checked="$department->is_active ?? true" switch help="New employees can only be placed in active departments." />
