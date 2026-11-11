<x-layouts::app :title="'Edit '.$quotation->displayNumber()">
    <x-page-header :title="'Edit '.$quotation->displayNumber()" subtitle="Only drafts can be edited. Totals are recalculated on the server when you save."
        :breadcrumbs="['Quotations' => route('quotations.index'), $quotation->displayNumber() => route('quotations.show', $quotation), 'Edit' => null]" />

    <form method="POST" action="{{ route('quotations.update', $quotation) }}" novalidate>
        @csrf
        @method('PUT')
        @include('quotations._form')
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" name="submit_now" value="1" class="btn btn-primary">Save and submit</button>
            <button type="submit" class="btn btn-outline-primary">Save draft</button>
            <a href="{{ route('quotations.show', $quotation) }}" class="btn btn-link text-secondary">Cancel</a>
        </div>
    </form>
</x-layouts::app>
