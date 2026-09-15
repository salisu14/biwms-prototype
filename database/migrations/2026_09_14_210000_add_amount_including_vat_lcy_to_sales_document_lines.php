<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complete the FCY/LCY monetary pair for the line-level "amount including VAT"
 * component on Sales document lines.
 *
 * Phase 3A added the other line LCY columns (unit price, line total, line
 * amount, discount, VAT) but the gross line amount (`amount_including_vat`)
 * participates in the real Sales line calculation, so its LCY equivalent was
 * missing. This is additive and nullable only: no historical row is backfilled
 * and no document amount is copied into an LCY column.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{0: int, 1: int}>
     */
    private array $tables = [
        'sales_order_lines' => [15, 4],
        'posted_sales_invoice_lines' => [15, 4],
        'posted_sales_credit_memo_lines' => [15, 4],
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName => [$precision, $scale]) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'amount_including_vat_lcy')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($precision, $scale): void {
                $table->decimal('amount_including_vat_lcy', $precision, $scale)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName => [$precision, $scale]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'amount_including_vat_lcy')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('amount_including_vat_lcy');
            });
        }
    }
};
