<x-form.input name="name" label="Name" :value="$category->name" maxlength="100" required />
<x-form.select name="parent_id" label="Parent category" :options="$parents" :value="$category->parent_id" placeholder="None (top level)" />
<x-form.input name="description" label="Description" :value="$category->description" maxlength="255" />
