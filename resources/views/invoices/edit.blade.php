<x-layouts::app :title="'Edit '.$invoice->displayNumber()">
    <x-page-header :title="'Edit '.$invoice->displayNumber()" subtitle="Only drafts can be edited. Totals are recalculated on the server when you save."
        :breadcrumbs="['Invoices' => route('invoices.index'), $invoice->displayNumber() => route('invoices.show', $invoice), 'Edit' => null]" />

    <form method="POST" action="{{ route('invoices.update', $invoice) }}" novalidate>
        @csrf
        @method('PUT')
        @include('invoices._form')
        <div class="d-flex flex-wrap gap-2 mt-3">
            @can('issue', $invoice)
                <button type="submit" name="issue_now" value="1" class="btn btn-primary">Save and issue</button>
            @endcan
            <button type="submit" class="btn btn-outline-primary">Save draft</button>
            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-link text-secondary">Cancel</a>
        </div>
    </form>
</x-layouts::app>
