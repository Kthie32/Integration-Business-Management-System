<x-layouts::app :title="'Edit '.$expense->displayNumber()">
    <x-page-header :title="'Edit '.$expense->displayNumber()"
        :breadcrumbs="['Expenses' => route('expenses.index'), $expense->displayNumber() => route('expenses.show', $expense), 'Edit' => null]" />

    <form method="POST" action="{{ route('expenses.update', $expense) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('expenses._form')
    </form>
</x-layouts::app>
