<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Illuminate\Support\Facades\DB;
use DomainException;

class GeneralLedgerService
{
    public function post(array $lines, array $meta = []): void
    {
        DB::transaction(function () use ($lines, $meta) {

            $totalDebit = collect($lines)->sum('debit');
            $totalCredit = collect($lines)->sum('credit');

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                throw new \Exception('Journal not balanced');
            }

            $transactionNumber = $this->generateTransactionNumber();

            foreach ($lines as $line) {
                GlEntry::create([
                    'transaction_number' => $transactionNumber,
                    'chart_of_account_id' => $line['account_id'],
                    'debit_amount' => $line['debit'],
                    'credit_amount' => $line['credit'],
                    'amount' => $line['debit'] - $line['credit'],
                    'posting_date' => $meta['posting_date'] ?? now(),
                    'document_number' => $meta['document_number'] ?? null,
                    'description' => $line['description'] ?? null,
                    'user_id' => auth()->id(),
                ]);
            }
        });
    }

    public function getTrialBalance($startDate, $endDate)
    {
        return ChartOfAccount::withSum(['glEntries as debit' => function ($q) use ($startDate, $endDate) {
            $q->whereBetween('posting_date', [$startDate, $endDate]);
        }], 'debit_amount')
            ->withSum(['glEntries as credit' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('posting_date', [$startDate, $endDate]);
            }], 'credit_amount')
            ->get();
    }

//    public function post(array $entries, string $reference, string $description): void
//    {
//        DB::transaction(function () use ($entries, $reference, $description) {
//
//            $totalDebit = 0;
//            $totalCredit = 0;
//
//            foreach ($entries as $entry) {
//                $totalDebit += $entry['debit'] ?? 0;
//                $totalCredit += $entry['credit'] ?? 0;
//            }
//
//            // 🚨 CRITICAL: enforce double-entry balance
//            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
//                throw new DomainException('GL not balanced: Debit != Credit');
//            }
//
//            $transactionNumber = $this->generateTransactionNumber();
//
//            foreach ($entries as $entry) {
//                DB::table('gl_entries')->insert([
//                    'transaction_number' => $transactionNumber,
//                    'account_id' => $entry['account_id'],
//                    'debit' => $entry['debit'] ?? 0,
//                    'credit' => $entry['credit'] ?? 0,
//                    'reference' => $reference,
//                    'description' => $description,
//                    'created_at' => now(),
//                    'updated_at' => now(),
//                ]);
//            }
//        });
//    }

    protected function generateTransactionNumber(): int
    {
        return (DB::table('gl_entries')->max('transaction_number') ?? 0) + 1;
    }
}
