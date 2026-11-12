<x-layouts::app title="New approval workflow">
    <x-page-header title="New approval workflow" :breadcrumbs="['Approval workflows' => route('approval-workflows.index'), 'New' => null]" />

    <form method="POST" action="{{ route('approval-workflows.store') }}" novalidate>
        @csrf
        @include('approvals.workflows._form')
    </form>
</x-layouts::app>
