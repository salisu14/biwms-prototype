<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'purchase_orders',
            'purchase_receipts',
            'purchase_invoices',
            'posted_purchase_invoices',
            'purchase_credit_memos',
            'posted_purchase_credit_memos',
            'payments',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'business_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('business_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('businesses')
                    ->nullOnDelete();

                $table->index('business_id');
            });
        }

        $this->backfillDeterministicOwnership();
    }

    public function down(): void
    {
        $tables = [
            'payments',
            'posted_purchase_credit_memos',
            'purchase_credit_memos',
            'posted_purchase_invoices',
            'purchase_invoices',
            'purchase_receipts',
            'purchase_orders',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'business_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('business_id');
            });
        }
    }

    private function backfillDeterministicOwnership(): void
    {
        if (Schema::hasTable('purchase_receipts') && Schema::hasColumn('purchase_receipts', 'business_id')) {
            $this->backfillFromParent('purchase_receipts', 'purchase_orders', [
                ['child_column' => 'purchase_order_id', 'parent_column' => 'id'],
            ]);
        }

        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'business_id')) {
            $this->backfillFromParent('purchase_invoices', 'purchase_orders', [
                ['child_column' => 'order_id', 'parent_column' => 'id'],
            ]);
            $this->backfillFromParent('posted_purchase_invoices', 'purchase_invoices', [
                ['child_column' => 'order_id', 'parent_column' => 'id'],
            ]);
            $this->backfillFromParent('purchase_credit_memos', 'purchase_invoices', [
                ['child_column' => 'corrects_invoice_id', 'parent_column' => 'id'],
            ]);
        }

        if (Schema::hasTable('purchase_credit_memos') && Schema::hasColumn('purchase_credit_memos', 'business_id')) {
            $this->backfillFromParent('posted_purchase_credit_memos', 'purchase_credit_memos', [
                ['child_column' => 'source_document_id', 'parent_column' => 'id'],
            ]);
        }

        // Payments are intentionally left nullable when no deterministic business
        // ownership can be derived from source document context.
    }

    private function backfillFromParent(string $childTable, string $parentTable, array $relations): void
    {
        if (! Schema::hasTable($childTable) || ! Schema::hasColumn($childTable, 'business_id')) {
            return;
        }

        if (! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, 'business_id')) {
            return;
        }

        foreach ($relations as $relation) {
            if (! Schema::hasColumn($childTable, $relation['child_column']) || ! Schema::hasColumn($parentTable, $relation['parent_column'])) {
                continue;
            }

            DB::statement(
                "update {$childTable} target
                 set business_id = source.business_id
                 from {$parentTable} source
                 where target.business_id is null
                   and target.{$relation['child_column']} = source.{$relation['parent_column']}
                   and source.business_id is not null"
            );
        }
    }
};
