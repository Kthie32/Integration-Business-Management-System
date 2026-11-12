<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Requisitions (FR-REQ-01..07) and the generic approval engine (FR-APR-01..02). Workflows are templates;
     * when a document is submitted its applicable steps are copied into `approvals`, so later edits to a
     * workflow never change requests already in progress.
     */
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->date('required_by_date')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('purpose', 1000);
            $table->decimal('estimated_total', 15, 2)->default(0);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 150)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->string('status', 30)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'status']);
            $table->index(['requested_by', 'status']);
        });

        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->string('unit', 20)->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('estimated_unit_cost', 15, 2)->default(0);
            $table->decimal('estimated_total', 15, 2)->default(0);
            $table->decimal('approved_quantity', 15, 3)->nullable();
            $table->decimal('fulfilled_quantity', 15, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('requisition_fulfilments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->restrictOnDelete();
            $table->foreignId('requisition_item_id')->constrained()->restrictOnDelete();
            $table->string('fulfilment_type', 20);
            $table->decimal('quantity', 15, 3);
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40)->index();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name', 100)->nullable();
            $table->string('approver_type', 20);
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['approval_workflow_id', 'sequence']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->foreignId('approval_workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->unsignedSmallInteger('sequence');
            $table->string('name', 100);
            $table->string('approver_type', 20);
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('comment', 1000)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id', 'round']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE requisition_items ADD CONSTRAINT requisition_items_quantities_valid CHECK (quantity > 0 AND fulfilled_quantity >= 0)');
            DB::statement('ALTER TABLE requisition_fulfilments ADD CONSTRAINT requisition_fulfilments_quantity_positive CHECK (quantity > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('requisition_fulfilments');
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('requisitions');
    }
};
