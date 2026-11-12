<x-layouts::app :title="'Edit requisition '.$requisition->displayNumber()">
    <x-page-header :title="'Edit requisition '.$requisition->displayNumber()"
        :subtitle="$requisition->status === App\Enums\RequisitionStatus::Returned ? 'Returned for revision. Make the changes asked for, then submit again.' : null"
        :breadcrumbs="['Requisitions' => route('requisitions.index'), $requisition->displayNumber() => route('requisitions.show', $requisition), 'Edit' => null]" />

    <form method="POST" action="{{ route('requisitions.update', $requisition) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('requisitions._form')
    </form>
</x-layouts::app>
