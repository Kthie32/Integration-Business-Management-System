<x-layouts::app title="Edit user">
    <x-page-header :title="'Edit '.$user->name" :back="route('admin.users.show', $user)" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
                @csrf
                @method('PUT')
                @include('admin.users._form')

                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
</x-layouts::app>
