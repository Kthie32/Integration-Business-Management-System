<x-layouts::app title="Receive goods">
    <x-page-header title="Receive goods" subtitle="Record items delivered by a supplier. Stock is added when the note is posted."
        :breadcrumbs="['Goods received' => route('goods-receipts.index'), 'New' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('goods-receipts.store') }}" novalidate>
                @csrf
                @include('inventory.goods-receipts._form')
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="post_now" value="1" class="btn btn-primary">Save and post</button>
                    <button type="submit" class="btn btn-outline-primary">Save as draft</button>
                    <a href="{{ route('goods-receipts.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
