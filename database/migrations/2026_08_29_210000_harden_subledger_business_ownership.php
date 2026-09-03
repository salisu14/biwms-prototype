<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'customer_ledger_entries',
            'vendor_ledger_entries',
            'payment_applications',
            'posted_sales_invoices',
            'posted_sales_credit_memos',
            'sales_orders',
            'sales_invoices',
            'sales_credit_memos',
        ] as $tableName) {
            $this->addBusinessColumn($tableName);
        }

        if (Schema::hasTable('customer_ledger_applications')) {
            $this->addBusinessColumn('customer_ledger_applications');
        }

        $this->addIndex('customer_ledger_entries', ['business_id', 'customer_id', 'posting_date'], 'customer_ledger_business_customer_date_idx');
        $this->addIndex('vendor_ledger_entries', ['business_id', 'vendor_id', 'posting_date'], 'vendor_ledger_business_vendor_date_idx');
        $this->addIndex('payment_applications', ['business_id', 'payment_id', 'reversed'], 'payment_apps_business_payment_reversed_idx');
        $this->addIndex('customer_ledger_applications', ['business_id', 'reversed'], 'customer_ledger_apps_business_reversed_idx');
    }

    public function down(): void
    {
        foreach ([
            'customer_ledger_applications',
            'payment_applications',
            'vendor_ledger_entries',
            'customer_ledger_entries',
            'posted_sales_credit_memos',
            'posted_sales_invoices',
            'sales_credit_memos',
            'sales_invoices',
            'sales_orders',
        ] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'business_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('business_id');
            });
        }
    }

    private function addBusinessColumn(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'business_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->foreignId('business_id')
                ->nullable()
                ->constrained('businesses')
                ->nullOnDelete();
        });
    }

    /** @param array<int, string> $columns */
    private function addIndex(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'business_id')) {
            return;
        }

        $existing = collect(Schema::getIndexes($tableName))->pluck('name');

        if (! $existing->contains($indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
                $table->index($columns, $indexName);
            });
        }
    }
};
