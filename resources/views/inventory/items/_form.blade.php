<div class="row">
    <div class="col-md-8">
        <x-form.input name="name" label="Name" :value="$item->name" maxlength="200" required autofocus />
    </div>
    <div class="col-md-4">
        <x-form.select name="type" label="Type" :options="$types" :value="$item->type" required
            help="Services carry no stock. The type cannot change once stock has moved." />
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <x-form.select name="item_category_id" label="Category" :options="$categories" :value="$item->item_category_id" placeholder="None" />
    </div>
    <div class="col-md-3">
        <x-form.input name="unit" label="Unit of measure" :value="$item->unit" maxlength="20" required help="e.g. pcs, kg, litre, box" />
    </div>
    <div class="col-md-4">
        <x-form.select name="tax_rate_id" label="Tax rate" :options="$taxRates" :value="$item->tax_rate_id" placeholder="No tax" />
    </div>
</div>

<x-form.textarea name="description" label="Description" :value="$item->description" rows="2" maxlength="2000" />

<h2 class="h6 mt-2 mb-3">Prices</h2>
<div class="row">
    <div class="col-md-4">
        <x-form.input name="selling_price" label="Selling price" type="number" step="0.01" min="0" :value="$item->selling_price !== null ? (string) $item->selling_price : null" required help="Before tax." />
    </div>
    <div class="col-md-4">
        <x-form.input name="cost_price" label="Standard cost" type="number" step="0.01" min="0" :value="$item->cost_price !== null ? (string) $item->cost_price : null"
            help="Used as a default; stock is valued at weighted average cost." />
    </div>
</div>

<h2 class="h6 mt-2 mb-3">Stock control</h2>
<div class="row">
    <div class="col-md-4">
        <x-form.input name="min_stock_level" label="Minimum stock level" type="number" step="0.001" min="0" :value="$item->min_stock_level !== null ? (string) $item->min_stock_level : null"
            help="An alert is raised at or below this level. 0 turns alerts off." />
    </div>
    <div class="col-md-4">
        <x-form.input name="reorder_qty" label="Reorder quantity" type="number" step="0.001" min="0" :value="$item->reorder_qty !== null ? (string) $item->reorder_qty : null" />
    </div>
</div>

<x-form.checkbox name="is_active" label="Active" :checked="$item->is_active ?? true" help="Inactive items cannot be received, issued or sold." switch />
