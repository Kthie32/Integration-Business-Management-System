@php
    $maxSize = intdiv(App\Http\Requests\Procurement\StoreRequisitionRequest::MAX_KILOBYTES, 1024);
    $lineRows = old('lines', $requisition->exists
        ? $requisition->items->map(fn ($line) => [
            'item_id' => $line->item_id,
            'description' => $line->description,
            'quantity' => App\Support\Quantity::format($line->quantity),
            'estimated_unit_cost' => (string) $line->estimated_unit_cost,
        ])->all()
        : []);
    if (count($lineRows) === 0) {
        $lineRows = [['item_id' => null, 'description' => null, 'quantity' => null, 'estimated_unit_cost' => null]];
    }
@endphp
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header fw-semibold">Request</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.select name="department_id" label="Department" :options="$departments" :value="$requisition->department_id" placeholder="Choose a department" required />
                    </div>
                    <div class="col-md-3">
                        <x-form.input name="required_by_date" label="Needed by" type="date" :value="$requisition->required_by_date?->toDateString()" :min="today()->toDateString()" />
                    </div>
                    <div class="col-md-3">
                        <x-form.select name="priority" label="Priority" :options="$priorities" :value="$requisition->priority" required />
                    </div>
                </div>
                <x-form.textarea name="purpose" label="Purpose / justification" :value="$requisition->purpose" rows="3" maxlength="1000" required
                    help="Why it is needed. Approvers see this first." />
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header fw-semibold">Supporting document</div>
            <div class="card-body">
                @if ($requisition->hasAttachment())
                    <p class="small mb-2"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ $requisition->attachment_name }}</p>
                    <x-form.checkbox name="remove_attachment" label="Remove this attachment" />
                @endif
                <x-form.input name="attachment" :label="$requisition->hasAttachment() ? 'Replace with' : 'Quotation, specification or photo'" type="file" accept=".pdf,.jpg,.jpeg,.png"
                    :help="'Optional. PDF, JPG or PNG, up to '.$maxSize.' MB.'" />
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold">Lines</div>
    <div class="card-body">
        @error('lines')
            <div class="alert alert-danger small py-2">{{ $message }}</div>
        @enderror
        <p class="small text-secondary">Choose a catalogue item, or leave it as "Not in catalogue" and describe what is needed. Costs are estimates for approval; the actual cost is captured on fulfilment.</p>

        <div data-line-editor>
            <div class="table-responsive">
                <table class="table align-middle mb-2">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="min-width: 14rem;">Catalogue item</th>
                            <th scope="col" style="min-width: 14rem;">Description</th>
                            <th scope="col" style="width: 9rem;">Quantity</th>
                            <th scope="col" style="width: 10rem;">Est. unit cost</th>
                            <th scope="col" class="text-end" style="width: 8rem;">Est. total</th>
                            <th scope="col" style="width: 3rem;"><span class="visually-hidden">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody data-lines>
                        @foreach ($lineRows as $index => $line)
                            @include('requisitions._line', ['index' => $index, 'line' => $line])
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Estimated total</th>
                            <th class="text-end" data-grand-total>0</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <template data-line-template>
                @include('requisitions._line', ['index' => '__INDEX__', 'line' => []])
            </template>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-line><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add line</button>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mt-3">
    <button type="submit" name="action" value="save" class="btn btn-outline-primary">Save draft</button>
    <button type="submit" name="action" value="submit" class="btn btn-primary">Save and submit for approval</button>
    <a href="{{ $requisition->exists ? route('requisitions.show', $requisition) : route('requisitions.index') }}" class="btn btn-link text-secondary">Cancel</a>
</div>
