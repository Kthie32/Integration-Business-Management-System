<x-layouts::app title="Item categories">
    <x-page-header title="Item categories" subtitle="Group items for searching and reporting. Categories nest one level deep." />

    <div class="row g-4">
        <div class="col-lg-7">
            <x-data-table :empty="$categories->isEmpty()" empty-message="No categories yet." empty-icon="tags">
                <x-slot:head>
                    <tr><th scope="col">Category</th><th scope="col" class="text-end">Items</th><th scope="col"></th></tr>
                </x-slot:head>

                @foreach ($categories as $parent)
                    @foreach ([$parent, ...$parent->children] as $category)
                        <tr>
                            <td @class(['ps-4' => $category->parent_id !== null])>
                                @if ($category->parent_id !== null)<i class="bi bi-arrow-return-right text-secondary me-1" aria-hidden="true"></i>@endif
                                <span class="fw-semibold">{{ $category->name }}</span>
                                @if ($category->description)
                                    <div class="small text-secondary">{{ $category->description }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('items.index', ['category' => $category->id]) }}" class="text-decoration-none">{{ $category->items_count }}</a>
                            </td>
                            <td class="text-end text-nowrap">
                                @can('update', $category)
                                    <a href="{{ route('item-categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                @endcan
                                @can('delete', $category)
                                    <form method="POST" action="{{ route('item-categories.destroy', $category) }}" class="d-inline" data-confirm="Delete the category {{ $category->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete {{ $category->name }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </x-data-table>
        </div>

        @can('create', App\Models\ItemCategory::class)
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header fw-semibold">New category</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('item-categories.store') }}" novalidate>
                            @csrf
                            @include('inventory.categories._form')
                            <button type="submit" class="btn btn-primary">Add category</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
</x-layouts::app>
