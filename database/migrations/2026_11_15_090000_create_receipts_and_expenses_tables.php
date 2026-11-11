<?php

use App\Enums\DocumentType;
use App\Services\System\NumberingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Receipts are issued by PaymentService in the same transaction as their payment (FR-PAY-04), one per
     * payment. Payments recorded before this phase are given receipts here, numbered in payment order.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('receipt_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->index();
            $table->timestamp('issued_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->date('expense_date')->index();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('payee_name', 150)->nullable();
            $table->string('description', 500);
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->string('payment_method', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 150)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->nullableMorphs('source');
            $table->string('status', 20)->index();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expense_date']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE receipts ADD CONSTRAINT receipts_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amounts_valid CHECK (amount > 0 AND tax_amount >= 0 AND tax_amount <= amount)');
        }

        $this->backfillReceipts();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('receipts');
    }

    /**
     * Give every payment recorded before receipts existed its receipt, so the rule "one receipt per
     * payment" holds for old data too.
     */
    private function backfillReceipts(): void
    {
        if (! DB::table('payments')->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $numbering = app(NumberingService::class);

            DB::table('payments')->orderBy('id')->each(function (object $payment) use ($numbering): void {
                $reversed = $payment->status === 'reversed';

                DB::table('receipts')->insert([
                    'number' => $numbering->next(DocumentType::Receipt, Carbon::parse($payment->payment_date)),
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'customer_id' => $payment->customer_id,
                    'receipt_date' => $payment->payment_date,
                    'amount' => $payment->amount,
                    'status' => $reversed ? 'void' : 'valid',
                    'issued_at' => $payment->created_at ?? now(),
                    'voided_at' => $reversed ? $payment->reversed_at : null,
                    'voided_by' => $reversed ? $payment->reversed_by : null,
                    'void_reason' => $reversed ? $payment->reversal_reason : null,
                    'created_by' => $payment->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        });
    }
};
