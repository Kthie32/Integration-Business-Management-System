<x-layouts::app title="New quotation">
    <x-page-header title="New quotation" subtitle="Totals are recalculated on the server when you save."
        :breadcrumbs="['Quotations' => route('quotations.index'), 'New' => null]" />

    <form method="POST" action="{{ route('quotations.store') }}" novalidate>
        @csrf
        @include('quotations._form')
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" name="submit_now" value="1" class="btn btn-primary">Save and submit</button>
            <button type="submit" class="btn btn-outline-primary">Save as draft</button>
            <a href="{{ route('quotations.index') }}" class="btn btn-link text-secondary">Cancel</a>
        </div>
    </form>
</x-layouts::app>
