<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Payroll (FR-PRL-01..09): pay components, effective-dated salary structures and statutory rules, monthly
     * payroll runs with one item per employee and a stored line breakdown, so a payslip can always be
     * reproduced exactly as it was approved, whatever changes later.
     */
    public function up(): void
    {
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->string('calculation', 20);
            $table->decimal('default_value', 15, 4)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_nssf_applicable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('basic_salary', 15, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('salary_structure_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 15, 4);
            $table->timestamps();

            $table->unique(['salary_structure_id', 'pay_component_id'], 'salary_structure_component_unique');
        });

        Schema::create('statutory_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->json('rules');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['code', 'effective_from']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('run_type', 20)->default('regular');
            $table->string('status', 30)->index();
            $table->date('payment_date')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_basic', 15, 2)->default(0);
            $table->decimal('total_allowances', 15, 2)->default(0);
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_paye', 15, 2)->default(0);
            $table->decimal('total_nssf_employee', 15, 2)->default(0);
            $table->decimal('total_nssf_employer', 15, 2)->default(0);
            $table->decimal('total_lst', 15, 2)->default(0);
            $table->decimal('total_other_deductions', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->decimal('total_employer_cost', 15, 2)->default(0);
            $table->json('statutory_rate_ids')->nullable();
            $table->json('skipped_employees')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('paid_at')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamps();

            $table->unique(['period_year', 'period_month', 'run_type']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('description', 150);
            $table->decimal('amount', 15, 2);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_nssf_applicable')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payslip_number', 40)->nullable()->unique();
            $table->string('employee_number', 30);
            $table->string('employee_name', 255);
            $table->string('department_name', 150)->nullable();
            $table->string('job_title', 150)->nullable();
            $table->string('tin', 20)->nullable();
            $table->string('nssf_number', 30)->nullable();
            $table->boolean('is_resident_for_tax')->default(true);
            $table->string('payment_method', 20);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('mobile_money_number', 30)->nullable();
            $table->decimal('basic_salary', 15, 2);
            $table->decimal('total_allowances', 15, 2);
            $table->decimal('gross_pay', 15, 2);
            $table->decimal('taxable_pay', 15, 2);
            $table->decimal('nssf_pay', 15, 2);
            $table->decimal('paye', 15, 2);
            $table->decimal('nssf_employee', 15, 2);
            $table->decimal('nssf_employer', 15, 2);
            $table->decimal('lst', 15, 2);
            $table->decimal('other_deductions', 15, 2);
            $table->decimal('total_deductions', 15, 2);
            $table->decimal('net_pay', 15, 2);
            $table->string('payment_status', 20)->default('pending');
            $table->date('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payroll_item_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('description', 150);
            $table->string('kind', 30);
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_item_lines');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('statutory_rates');
        Schema::dropIfExists('salary_structure_components');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('pay_components');
    }
};
