<x-layouts::app title="New role">
    <x-page-header title="New role" subtitle="Choose the permissions this role grants." :back="route('admin.roles.index')" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.roles.store') }}" novalidate>
                @csrf
                @include('admin.roles._form')

                <button type="submit" class="btn btn-primary">Create role</button>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
</x-layouts::app>
