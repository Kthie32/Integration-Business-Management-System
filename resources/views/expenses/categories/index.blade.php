<x-layouts::app title="Expense categories">
    <x-page-header title="Expense categories" subtitle="Group expenses for reporting. A category that has expenses can be deactivated but not deleted."
        :breadcrumbs="['Expenses' => route('expenses.index'), 'Categories' => null]" />

    <div class="row g-4">
        <div class="col-lg-7">
            <x-data-table :empty="$categories->isEmpty()" empty-message="No categories yet." empty-icon="tags">
                <x-slot:head>
                    <tr><th scope="col">Category</th><th scope="col">Status</th><th scope="col" class="text-end">Expenses</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr>
                </x-slot:head>

                @foreach ($categories as $category)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $category->name }}</span>
                            @if ($category->code)<code class="ms-1">{{ $category->code }}</code>@endif
                            @if ($category->description)
                                <div class="small text-secondary">{{ $category->description }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($category->is_active)
                                <x-status-badge label="Active" color="success" />
                            @else
                                <x-status-badge label="Inactive" color="secondary" />
                            @endif
                        </td>
                        <td class="text-end">
                            @can('viewAny', App\Models\Expense::class)
                                <a href="{{ route('expenses.index', ['category' => $category->id]) }}" class="text-decoration-none">{{ $category->expenses_count }}</a>
                            @else
                                {{ $category->expenses_count }}
                            @endcan
                        </td>
                        <td class="text-end text-nowrap">
                            @can('update', $category)
                                <a href="{{ route('expense-categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            @endcan
                            @can('delete', $category)
                                @if ($category->expenses_count === 0)
                                    <form method="POST" action="{{ route('expense-categories.destroy', $category) }}" class="d-inline" data-confirm="Delete the category {{ $category->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete {{ $category->name }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>

        @can('create', App\Models\ExpenseCategory::class)
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header fw-semibold">New category</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('expense-categories.store') }}" novalidate>
                            @csrf
                            @include('expenses.categories._form')
                            <button type="submit" class="btn btn-primary">Add category</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
</x-layouts::app>
