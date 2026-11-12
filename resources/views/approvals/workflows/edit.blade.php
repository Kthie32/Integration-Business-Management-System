<x-layouts::app :title="'Edit '.$workflow->name">
    <x-page-header :title="'Edit '.$workflow->name" subtitle="Changes apply to requests submitted from now on."
        :breadcrumbs="['Approval workflows' => route('approval-workflows.index'), $workflow->name => null]" />

    <form method="POST" action="{{ route('approval-workflows.update', $workflow) }}" novalidate>
        @csrf
        @method('PUT')
        @include('approvals.workflows._form')
    </form>
</x-layouts::app>
