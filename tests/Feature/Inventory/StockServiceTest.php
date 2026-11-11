<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStock;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private Item $item;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
        $this->item = Item::factory()->create();
        $this->warehouse = Warehouse::factory()->default()->create();
    }

    private function level(?Warehouse $warehouse = null): StockLevel
    {
        return StockLevel::query()->where('item_id', $this->item->id)->where('warehouse_id', ($warehouse ?? $this->warehouse)->id)->firstOrFail();
    }

    public function test_receiving_stock_records_a_movement_and_updates_the_balance(): void
    {
        $movement = $this->stock->receive($this->item, $this->warehouse, '10', '1500');

        $this->assertSame(StockMovementType::GoodsReceived, $movement->type);
        $this->assertSame(1, $movement->direction);
        $this->assertSame('10.000', (string) $movement->balance_after);
        $this->assertSame('10.000', (string) $this->level()->quantity);
        $this->assertSame('1500.0000', (string) $this->level()->average_cost);
    }

    public function test_receipts_at_different_costs_are_blended_into_a_weighted_average(): void
    {
        $this->stock->receive($this->item, $this->warehouse, '10', '1000');
        $this->stock->receive($this->item, $this->warehouse, '30', '2000');

        $this->assertSame('40.000', (string) $this->level()->quantity);
        $this->assertSame('1750.0000', (string) $this->level()->average_cost);
    }

    public function test_weighted_average_is_rounded_half_up_to_four_decimals(): void
    {
        $this->stock->receive($this->item, $this->warehouse, '3', '1000');
        $this->stock->receive($this->item, $this->warehouse, '3', '1000.0001');

        $this->assertSame('1000.0001', (string) $this->level()->average_cost);
    }

    public function test_issuing_stock_leaves_at_the_current_average_cost_without_changing_it(): void
    {
        $this->stock->receive($this->item, $this->warehouse, '10', '1000');
        $this->stock->receive($this->item, $this->warehouse, '10', '2000');

        $movement = $this->stock->issue($this->item, $this->warehouse, '4.5');

        $this->assertSame(-1, $movement->direction);
        $this->assertSame('1500.0000', (string) $movement->unit_cost);
        $this->assertSame('15.500', (string) $this->level()->quantity);
        $this->assertSame('1500.0000', (string) $this->level()->average_cost);
    }

    public function test_issuing_more_than_is_available_is_rejected_and_nothing_is_written(): void
    {
        $this->stock->receive($this->item, $this->warehouse, '5', '1000');

        try {
            $this->stock->issue($this->item, $this->warehouse, '5.001');
            $this->fail('Expected InsufficientStock.');
        } catch (InsufficientStock $exception) {
            $this->assertStringContainsString('5 available, 5.001 requested', $exception->getMessage());
        }

        $this->assertSame('5.000', (string) $this->level()->quantity);
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_the_database_refuses_a_negative_balance_even_if_the_service_is_bypassed(): void
    {
        $this->stock->receive($this->item, $this->warehouse, '1', '1000');

        $this->expectException(QueryException::class);

        DB::table('stock_levels')->where('item_id', $this->item->id)->update(['quantity' => -1]);
    }

    public function test_service_items_inactive_items_and_inactive_warehouses_cannot_move_stock(): void
    {
        $service = Item::factory()->service()->create();
        $inactiveItem = Item::factory()->inactive()->create();
        $inactiveWarehouse = Warehouse::factory()->inactive()->create();

        foreach ([[$service, $this->warehouse], [$inactiveItem, $this->warehouse], [$this->item, $inactiveWarehouse]] as [$item, $warehouse]) {
            try {
                $this->stock->receive($item, $warehouse, '1', '100');
                $this->fail('Expected the movement to be refused.');
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_zero_quantities_are_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->stock->receive($this->item, $this->warehouse, '0.0004', '100');
    }

    public function test_transfer_moves_stock_and_its_average_cost_to_the_other_warehouse(): void
    {
        $branch = Warehouse::factory()->create();
        $this->stock->receive($this->item, $this->warehouse, '10', '1200');
        $this->stock->receive($this->item, $branch, '10', '2000');

        [$out, $in] = $this->stock->transfer($this->item, $this->warehouse, $branch, '10');

        $this->assertSame(StockMovementType::TransferOut, $out->type);
        $this->assertTrue($in->reference->is($out));
        $this->assertSame('0.000', (string) $this->level()->quantity);
        $this->assertSame('20.000', (string) $this->level($branch)->quantity);
        $this->assertSame('1600.0000', (string) $this->level($branch)->average_cost);
    }

    public function test_transfer_to_the_same_warehouse_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->stock->transfer($this->item, $this->warehouse, $this->warehouse, '1');
    }

    public function test_reversing_a_receipt_removes_it_at_its_own_cost(): void
    {
        $this->stock->receive($this->item, $this->warehouse, '10', '1000');
        $second = $this->stock->receive($this->item, $this->warehouse, '10', '2000');

        $reversal = $this->stock->reverse($second)->sole();

        $this->assertSame(StockMovementType::Reversal, $reversal->type);
        $this->assertSame(-1, $reversal->direction);
        $this->assertSame($second->id, $reversal->reverses_movement_id);
        $this->assertSame('10.000', (string) $this->level()->quantity);
        $this->assertSame('1000.0000', (string) $this->level()->average_cost);
    }

    public function test_reversing_an_issue_returns_the_stock_at_the_cost_it_left_at(): void
    {
        $this->stock->receive($this->item, $this->warehouse, '10', '1000');
        $issue = $this->stock->issue($this->item, $this->warehouse, '4');

        $this->stock->reverse($issue);

        $this->assertSame('10.000', (string) $this->level()->quantity);
        $this->assertSame('1000.0000', (string) $this->level()->average_cost);
    }

    public function test_a_receipt_whose_stock_was_already_issued_cannot_be_reversed(): void
    {
        $receipt = $this->stock->receive($this->item, $this->warehouse, '10', '1000');
        $this->stock->issue($this->item, $this->warehouse, '8');

        $this->expectException(InsufficientStock::class);

        $this->stock->reverse($receipt);
    }

    public function test_a_movement_cannot_be_reversed_twice_and_a_reversal_cannot_be_reversed(): void
    {
        $receipt = $this->stock->receive($this->item, $this->warehouse, '10', '1000');
        $reversal = $this->stock->reverse($receipt)->sole();

        foreach ([$receipt, $reversal] as $movement) {
            try {
                $this->stock->reverse($movement);
                $this->fail('Expected the reversal to be refused.');
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('0.000', (string) $this->level()->quantity);
    }

    public function test_reversing_one_leg_of_a_transfer_reverses_both(): void
    {
        $branch = Warehouse::factory()->create();
        $this->stock->receive($this->item, $this->warehouse, '10', '1000');
        [, $in] = $this->stock->transfer($this->item, $this->warehouse, $branch, '6');

        $reversals = $this->stock->reverse($in);

        $this->assertCount(2, $reversals);
        $this->assertSame('10.000', (string) $this->level()->quantity);
        $this->assertSame('0.000', (string) $this->level($branch)->quantity);
    }

    public function test_reverse_for_a_document_reverses_each_of_its_movements_once(): void
    {
        $document = Warehouse::factory()->create();
        $this->stock->receive($this->item, $this->warehouse, '5', '1000', StockMovementType::GoodsReceived, $document);
        $this->stock->receive($this->item, $this->warehouse, '3', '1000', StockMovementType::GoodsReceived, $document);

        $this->assertCount(2, $this->stock->reverseFor($document));
        $this->assertCount(0, $this->stock->reverseFor($document));
        $this->assertSame('0.000', (string) $this->level()->quantity);
    }

    public function test_opening_balance_is_only_allowed_before_any_other_movement(): void
    {
        $this->stock->recordOpeningBalance($this->item, $this->warehouse, '12', '900');

        $this->assertSame('12.000', (string) $this->level()->quantity);

        $this->expectException(BusinessRuleException::class);

        $this->stock->recordOpeningBalance($this->item, $this->warehouse, '1', '900');
    }

    public function test_movements_cannot_be_edited_or_deleted(): void
    {
        $movement = $this->stock->receive($this->item, $this->warehouse, '1', '1000');

        try {
            $movement->forceFill(['quantity' => '2'])->save();
            $this->fail('Expected the update to be refused.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(LogicException::class);

        $movement->delete();
    }

    public function test_a_failed_movement_inside_a_larger_transaction_rolls_back_earlier_movements(): void
    {
        $other = Item::factory()->create();

        try {
            DB::transaction(function () use ($other): void {
                $this->stock->receive($this->item, $this->warehouse, '5', '1000');
                $this->stock->issue($other, $this->warehouse, '1');
            });
        } catch (InsufficientStock) {
            // Expected.
        }

        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame('0.000', (string) ($this->stock->available($this->item, $this->warehouse)));
    }
}
