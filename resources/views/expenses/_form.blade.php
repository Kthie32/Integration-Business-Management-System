@php($maxSize = intdiv(App\Http\Requests\Finance\StoreExpenseRequest::MAX_KILOBYTES, 1024))
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header fw-semibold">Expense</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <x-form.input name="expense_date" label="Date" type="date" :value="$expense->expense_date?->toDateString()" :max="today()->toDateString()" required />
                    </div>
                    <div class="col-md-8">
                        <x-form.select name="expense_category_id" label="Category" :options="$categories" :value="$expense->expense_category_id" placeholder="Choose a category" required
                            :help="$categories->isEmpty() ? 'No active categories yet. Ask a finance officer to add some.' : null" />
                    </div>
                </div>
                <x-form.input name="description" label="Description" :value="$expense->description" maxlength="500" required />
                <div class="row">
                    <div class="col-md-6">
                        <x-form.select name="supplier_id" label="Supplier" :options="$suppliers" :value="$expense->supplier_id" placeholder="None" />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="payee_name" label="Paid to (if not a supplier)" :value="$expense->payee_name" maxlength="150" />
                    </div>
                </div>
                <x-form.select name="department_id" label="Department" :options="$departments" :value="$expense->department_id" placeholder="Organization-wide" />
                <x-form.textarea name="notes" label="Notes" :value="$expense->notes" rows="2" maxlength="1000" />
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Payment</div>
            <div class="card-body">
                <x-form.input name="amount" label="Total amount (incl. tax)" type="number" step="0.01" min="0.01" :value="$expense->amount" required />
                <x-form.input name="tax_amount" label="Of which tax" type="number" step="0.01" min="0" :value="$expense->tax_amount" help="Leave 0 if no tax was charged." />
                <x-form.select name="payment_method" label="Paid by" :options="$methods" :value="$expense->payment_method" required />
                <x-form.input name="reference" label="Transaction reference" :value="$expense->reference" maxlength="100" help="Required for every method except cash." />
            </div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Attachment</div>
            <div class="card-body">
                @if ($expense->hasAttachment())
                    <p class="small mb-2"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ $expense->attachment_name }}</p>
                    <x-form.checkbox name="remove_attachment" label="Remove this attachment" />
                @endif
                <x-form.input name="attachment" :label="$expense->hasAttachment() ? 'Replace with' : 'Receipt or invoice'" type="file" accept=".pdf,.jpg,.jpeg,.png"
                    :help="'PDF, JPG or PNG, up to '.$maxSize.' MB.'" />
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mt-3">
    <button type="submit" name="action" value="save" class="btn btn-outline-primary">Save draft</button>
    <button type="submit" name="action" value="record" class="btn btn-primary">Save and record</button>
    <a href="{{ $expense->exists ? route('expenses.show', $expense) : route('expenses.index') }}" class="btn btn-link text-secondary">Cancel</a>
</div>
