{{-- Links a stock movement to the document that caused it, when the viewer can open that document. --}}
@php($reference = $movement->reference)
@if ($reference instanceof App\Models\GoodsReceipt)
    <a href="{{ route('goods-receipts.show', $reference) }}" class="text-decoration-none">{{ $reference->displayNumber() }}</a>
@elseif ($reference instanceof App\Models\StockAdjustment)
    <a href="{{ route('stock-adjustments.show', $reference) }}" class="text-decoration-none">{{ $reference->number }}</a>
@elseif ($reference instanceof App\Models\StockMovement)
    <span class="text-secondary">Transfer #{{ $reference->id }}</span>
@elseif ($reference !== null)
    <span class="text-secondary">{{ class_basename($reference) }} #{{ $reference->getKey() }}</span>
@else
    <span class="text-secondary">Movement #{{ $movement->id }}</span>
@endif
@if ($movement->isReversal())
    <span class="text-secondary">· reverses #{{ $movement->reverses_movement_id }}</span>
@endif
