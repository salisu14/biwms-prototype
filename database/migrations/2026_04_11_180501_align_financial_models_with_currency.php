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
        $tables = [
            'payments',
            'payment_applications',
            'customer_ledger_entries',
            'vendor_ledger_entries',
            'posted_sales_invoices',
            'posted_purchase_invoices',
            'posted_sales_credit_memos',
            'posted_purchase_credit_memos',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (! Schema::hasColumn($table->getTable(), 'currency_id')) {
                        $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
                    }
                });
            }
        }

        // Additional fields for Payment Applications
        if (Schema::hasTable('payment_applications')) {
            Schema::table('payment_applications', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_applications', 'amount_applied_lcy')) {
                    $table->decimal('amount_applied_lcy', 18, 4)->default(0);
                }
                if (! Schema::hasColumn('payment_applications', 'gain_loss_amount')) {
                    $table->decimal('gain_loss_amount', 18, 4)->default(0);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Additional fields for Payment Applications
        if (Schema::hasTable('payment_applications')) {
            Schema::table('payment_applications', function (Blueprint $table) {
                $table->dropColumn(['amount_applied_lcy', 'gain_loss_amount']);
            });
        }

        $tables = [
            'payments',
            'payment_applications',
            'customer_ledger_entries',
            'vendor_ledger_entries',
            'posted_sales_invoices',
            'posted_purchase_invoices',
            'posted_sales_credit_memos',
            'posted_purchase_credit_memos',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('currency_id');
                });
            }
        }
    }
};
