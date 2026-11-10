<x-layouts::app title="New user">
    <x-page-header title="New user" subtitle="The user receives an email with a link to set their own password." :back="route('admin.users.index')" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
                @csrf
                @include('admin.users._form')

                <button type="submit" class="btn btn-primary">Create user</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
</x-layouts::app>
