<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

final class LedgerSequenceAllocator
{
    public function nextGlEntryNumber(): int
    {
        return $this->nextSequenceValue(
            sequenceName: 'gl_entry_number_seq',
            advisoryLockName: 'gl_entries_entry_number',
            fallbackTable: 'gl_entries',
            fallbackColumn: 'entry_number',
        );
    }

    public function nextGlTransactionNumber(): int
    {
        return $this->nextSequenceValue(
            sequenceName: 'gl_transaction_number_seq',
            advisoryLockName: 'gl_entries_transaction_number',
            fallbackTable: 'gl_entries',
            fallbackColumn: 'transaction_number',
        );
    }

    private function nextSequenceValue(
        string $sequenceName,
        string $advisoryLockName,
        string $fallbackTable,
        string $fallbackColumn,
    ): int {
        if (DB::getDriverName() === 'pgsql') {
            $this->ensurePostgresSequence($sequenceName, $fallbackTable, $fallbackColumn);

            return (int) DB::scalar("select nextval('{$sequenceName}')");
        }

        DB::table($fallbackTable)->lockForUpdate()->count();

        return ((int) DB::table($fallbackTable)->max($fallbackColumn)) + 1;
    }

    private function ensurePostgresSequence(string $sequenceName, string $tableName, string $columnName): void
    {
        $exists = DB::scalar(
            'select exists(select 1 from pg_class where relkind = ? and relname = ?)',
            ['S', $sequenceName]
        );

        if (! $exists) {
            DB::statement("create sequence {$sequenceName}");
        }

        $maxLedgerNumber = (int) DB::table($tableName)->max($columnName);
        $sequenceLastValue = (int) DB::scalar("select last_value from {$sequenceName}");

        if ($sequenceLastValue <= $maxLedgerNumber) {
            DB::statement("select setval('{$sequenceName}', ?, false)", [$maxLedgerNumber + 1]);
        }
    }

    /**
     * @return array{gl_entry_number: string, gl_transaction_number: string}
     */
    public function classify(): array
    {
        return [
            'gl_entry_number' => 'internal ledger entry number; PostgreSQL sequence when available; gaps acceptable',
            'gl_transaction_number' => 'internal balanced transaction number; PostgreSQL sequence when available; gaps acceptable',
        ];
    }
}
