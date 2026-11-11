<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The stock ledger (append-only movements) and the current balance per item per warehouse.
     * CHECK constraints are the final guard against negative balances and zero-quantity movements.
     */
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->tinyInteger('direction');
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('balance_after', 15, 3);
            $table->nullableMorphs('reference');
            $table->foreignId('reverses_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamp('moved_at')->index();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'warehouse_id', 'moved_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT stock_levels_quantity_non_negative CHECK (quantity >= 0)');
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_direction_valid CHECK (direction IN (-1, 1))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
    }
};
