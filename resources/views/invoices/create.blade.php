<x-layouts::app title="New invoice">
    <x-page-header title="New invoice" subtitle="Totals are recalculated on the server when you save. The number is assigned when the invoice is issued."
        :breadcrumbs="['Invoices' => route('invoices.index'), 'New' => null]" />

    <form method="POST" action="{{ route('invoices.store') }}" novalidate>
        @csrf
        @include('invoices._form')
        <div class="d-flex flex-wrap gap-2 mt-3">
            @can('invoices.issue')
                <button type="submit" name="issue_now" value="1" class="btn btn-primary">Save and issue</button>
            @endcan
            <button type="submit" class="btn btn-outline-primary">Save as draft</button>
            <a href="{{ route('invoices.index') }}" class="btn btn-link text-secondary">Cancel</a>
        </div>
    </form>
</x-layouts::app>
