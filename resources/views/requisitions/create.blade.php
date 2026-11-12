<x-layouts::app title="New requisition">
    <x-page-header title="New requisition" subtitle="Ask for goods or services. It is numbered when you submit it for approval."
        :breadcrumbs="['Requisitions' => route('requisitions.index'), 'New' => null]" />

    <form method="POST" action="{{ route('requisitions.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('requisitions._form')
    </form>
</x-layouts::app>
