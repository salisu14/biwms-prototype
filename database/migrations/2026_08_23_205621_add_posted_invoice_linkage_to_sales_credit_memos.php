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
        if (! Schema::hasColumn('sales_credit_memos', 'posted_sales_invoice_id')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                $table->foreignId('posted_sales_invoice_id')
                    ->nullable()
                    ->after('sales_invoice_id')
                    ->constrained('posted_sales_invoices')
                    ->nullOnDelete();

                $table->index(['customer_id', 'posted_sales_invoice_id']);
            });
        }

        if (! Schema::hasColumn('sales_credit_memo_lines', 'posted_sales_invoice_line_id')) {
            Schema::table('sales_credit_memo_lines', function (Blueprint $table): void {
                $table->foreignId('posted_sales_invoice_line_id')
                    ->nullable()
                    ->after('sales_invoice_line_id')
                    ->constrained('posted_sales_invoice_lines')
                    ->nullOnDelete();

                $table->index('posted_sales_invoice_line_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sales_credit_memo_lines', 'posted_sales_invoice_line_id')) {
            Schema::table('sales_credit_memo_lines', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('posted_sales_invoice_line_id');
            });
        }

        if (Schema::hasColumn('sales_credit_memos', 'posted_sales_invoice_id')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                $table->dropIndex(['customer_id', 'posted_sales_invoice_id']);
                $table->dropConstrainedForeignId('posted_sales_invoice_id');
            });
        }
    }
};
