<x-layouts::app title="New expense">
    <x-page-header title="New expense" :breadcrumbs="['Expenses' => route('expenses.index'), 'New' => null]" />

    <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('expenses._form')
    </form>
</x-layouts::app>
