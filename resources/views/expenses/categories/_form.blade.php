<x-form.input name="name" label="Name" :value="$category->name" maxlength="100" required />
<x-form.input name="code" label="Code" :value="$category->code" maxlength="20" help="Optional short code for reports, e.g. RENT." />
<x-form.input name="description" label="Description" :value="$category->description" maxlength="255" />
<x-form.checkbox name="is_active" label="Active (available for new expenses)" :checked="$category->is_active" switch />
