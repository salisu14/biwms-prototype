<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_credit_memos', function (Blueprint $table): void {
            $table->dropForeign(['sales_invoice_id']);
        });

        Schema::table('sales_credit_memos', function (Blueprint $table): void {
            $table->foreignId('sales_invoice_id')
                ->nullable()
                ->change();

            $table->foreign('sales_invoice_id')
                ->references('id')
                ->on('sales_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_credit_memos', function (Blueprint $table): void {
            $table->dropForeign(['sales_invoice_id']);
        });

        Schema::table('sales_credit_memos', function (Blueprint $table): void {
            $table->foreignId('sales_invoice_id')
                ->nullable(false)
                ->change();

            $table->foreign('sales_invoice_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
