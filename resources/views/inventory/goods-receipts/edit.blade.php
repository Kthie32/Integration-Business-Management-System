<x-layouts::app :title="'Edit '.$receipt->displayNumber()">
    <x-page-header :title="'Edit '.$receipt->displayNumber()"
        :breadcrumbs="['Goods received' => route('goods-receipts.index'), $receipt->displayNumber() => route('goods-receipts.show', $receipt), 'Edit' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('goods-receipts.update', $receipt) }}" novalidate>
                @csrf
                @method('PUT')
                @include('inventory.goods-receipts._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save draft</button>
                    <a href="{{ route('goods-receipts.show', $receipt) }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
