<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable pricing state for direct (unlinked) Sales Invoice and Sales Credit
 * Memo lines, matching the Sales Order line contract.
 *
 * Additive and forward-only. Existing rows stay NULL (historical / unknown) and
 * are never reinterpreted as resolved or manual. `down()` removes only the
 * columns this phase added.
 *
 * Source discrimination:
 *   - `price_record_id`  -> `sales_prices` row id only;
 *   - `pricing_master_id` -> legacy `pricing_master` FK only;
 *   - `price_source`      -> category/source label.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $columns = ['price_source', 'pricing_master_id', 'price_record_id', 'pricing_status'];

    public function up(): void
    {
        $this->addColumns('sales_invoice_lines');
        $this->addColumns('sales_credit_memo_lines');
    }

    public function down(): void
    {
        $this->dropColumns('sales_invoice_lines');
        $this->dropColumns('sales_credit_memo_lines');
    }

    private function addColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'price_source')) {
                $blueprint->string('price_source', 50)->nullable();
            }

            if (! Schema::hasColumn($table, 'pricing_master_id')) {
                $blueprint->unsignedBigInteger('pricing_master_id')->nullable();
            }

            if (! Schema::hasColumn($table, 'price_record_id')) {
                $blueprint->unsignedBigInteger('price_record_id')->nullable();
            }

            if (! Schema::hasColumn($table, 'pricing_status')) {
                $blueprint->string('pricing_status', 20)->nullable();
            }
        });
    }

    private function dropColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            foreach ($this->columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
