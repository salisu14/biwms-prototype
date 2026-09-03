<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CUSTOMER_VALUES = [
        'SALES_INVOICE',
        'SALES_CREDIT_MEMO',
        'PAYMENT',
        'REFUND',
        'CREDIT_MEMO_APPLICATION',
        'FINANCE_CHARGE',
        'REMINDER',
        'BANK_TRANSFER',
        'CASH_RECEIPT',
        'ADJUSTMENT',
        'WRITE_OFF',
        'OPENING_BALANCE',
    ];

    private const VENDOR_VALUES = [
        'PURCHASE_INVOICE',
        'PURCHASE_CREDIT_MEMO',
        'PAYMENT',
        'REFUND',
        'CREDIT_MEMO_APPLICATION',
        'FINANCE_CHARGE',
        'ADJUSTMENT',
        'WRITE_OFF',
        'PAYMENT_DISCOUNT',
        'BANK_TRANSFER',
        'OPENING_BALANCE',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->replaceConstraint('customer_ledger_entries', 'customer_ledger_entries_document_type_check', self::CUSTOMER_VALUES);
        $this->replaceConstraint('vendor_ledger_entries', 'vendor_ledger_entries_document_type_check', self::VENDOR_VALUES);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->removeOpeningBalanceSupport('customer_ledger_entries', 'customer_ledger_entries_document_type_check', self::CUSTOMER_VALUES);
        $this->removeOpeningBalanceSupport('vendor_ledger_entries', 'vendor_ledger_entries_document_type_check', self::VENDOR_VALUES);
    }

    /**
     * @param  array<int, string>  $allowedValues
     */
    private function replaceConstraint(string $table, string $constraint, array $allowedValues): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'document_type')) {
            return;
        }

        $values = implode(', ', array_map(
            static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
            $allowedValues,
        ));

        DB::statement(sprintf('ALTER TABLE "%s" DROP CONSTRAINT IF EXISTS "%s"', $table, $constraint));
        DB::statement(sprintf(
            'ALTER TABLE "%s" ADD CONSTRAINT "%s" CHECK (document_type IN (%s))',
            $table,
            $constraint,
            $values,
        ));
    }

    /**
     * Never make an existing opening-balance row invalid during rollback.
     * The migration can be rolled back after removing those rows, or retained
     * as a no-op until the data is handled through an approved process.
     *
     * @param  array<int, string>  $allowedValues
     */
    private function removeOpeningBalanceSupport(string $table, string $constraint, array $allowedValues): void
    {
        if (Schema::hasTable($table)
            && DB::table($table)->where('document_type', 'OPENING_BALANCE')->exists()) {
            return;
        }

        $this->replaceConstraint($table, $constraint, array_slice($allowedValues, 0, -1));
    }
};
