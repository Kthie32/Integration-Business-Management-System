<x-layouts::app :title="$role->name">
    <x-page-header :title="$role->name" :subtitle="$readOnly ? 'View only' : 'Edit role'" :back="route('admin.roles.index')" />

    @if ($readOnly)
        <div class="alert alert-info small">
            @if ($role->isSuperAdministrator())
                The Super Administrator role always has every permission and cannot be changed.
            @else
                You cannot change this role. Roles you hold yourself can only be changed by a Super Administrator.
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            @if ($readOnly)
                @include('admin.roles._form')
            @else
                <form method="POST" action="{{ route('admin.roles.update', $role) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('admin.roles._form')

                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-link">Cancel</a>
                </form>
            @endif
        </div>
    </div>
</x-layouts::app>
