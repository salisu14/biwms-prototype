<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3D-1 shared subledger semantic foundation.
 *
 * Purely additive and nullable with NO DEFAULT. Existing rows keep NULL and are
 * never reinterpreted or backfilled:
 *
 *  - ledger_semantics_version NULL means legacy / unclassified;
 *  - version 2 means the base monetary columns hold LCY carrying values and
 *    original_* hold the document (FCY) amounts;
 *  - original_remaining_amount is the document-currency amount still open.
 *
 * Customer Ledger receives the schema capability only; no Customer Ledger
 * writer or reader is migrated in this phase. payment_applications gains the
 * recognition-LCY companion for the existing settlement-LCY column
 * (amount_applied_lcy is NOT redefined).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['customer_ledger_entries', 'vendor_ledger_entries'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'ledger_semantics_version')) {
                    $table->smallInteger('ledger_semantics_version')->nullable()->default(null);
                }

                if (! Schema::hasColumn($tableName, 'original_remaining_amount')) {
                    $table->decimal('original_remaining_amount', 15, 4)->nullable()->default(null);
                }
            });
        }

        if (Schema::hasTable('payment_applications')
            && ! Schema::hasColumn('payment_applications', 'document_amount_applied_lcy')) {
            Schema::table('payment_applications', function (Blueprint $table): void {
                $table->decimal('document_amount_applied_lcy', 18, 4)->nullable()->default(null);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_applications')
            && Schema::hasColumn('payment_applications', 'document_amount_applied_lcy')) {
            Schema::table('payment_applications', function (Blueprint $table): void {
                $table->dropColumn('document_amount_applied_lcy');
            });
        }

        foreach (['customer_ledger_entries', 'vendor_ledger_entries'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach (['original_remaining_amount', 'ledger_semantics_version'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
