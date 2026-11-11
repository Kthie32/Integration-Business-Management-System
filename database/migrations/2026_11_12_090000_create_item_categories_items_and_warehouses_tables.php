<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('parent_id')->nullable()->constrained('item_categories')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->foreignId('item_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20)->index();
            $table->string('unit', 20);
            $table->decimal('selling_price', 15, 4)->default(0);
            $table->decimal('cost_price', 15, 4)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('min_stock_level', 15, 3)->default(0);
            $table->decimal('reorder_qty', 15, 3)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('location')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
    }
};
