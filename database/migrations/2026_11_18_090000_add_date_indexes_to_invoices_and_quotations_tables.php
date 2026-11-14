<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 14 performance check: the invoice and quotation lists sort by document date, reports and the dashboard
     * filter by it, and the dashboard lists the latest issued invoices. Without these indexes each of those reads the
     * whole table and sorts it.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->index('invoice_date');
            $table->index('issued_at');
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->index('quote_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['invoice_date']);
            $table->dropIndex(['issued_at']);
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropIndex(['quote_date']);
        });
    }
};
