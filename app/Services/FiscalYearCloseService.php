<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalYearCloseService
{
    public function closeIncomeStatement(int $fiscalYear, int $userId): array
    {
        $period = AccountingPeriod::query()
            ->whereYear('start_date', '<=', $fiscalYear)
            ->whereYear('end_date', '>=', $fiscalYear)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'fiscal_year' => "No accounting period found for fiscal year {$fiscalYear}.",
            ]);
        }

        if (! $period->is_closed) {
            throw ValidationException::withMessages([
                'fiscal_year' => 'Close the accounting period before running year-end close.',
            ]);
        }

        $setup = GeneralLedgerSetup::instance();
        if (! $setup->retained_earnings_account_id) {
            throw ValidationException::withMessages([
                'retained_earnings_account_id' => 'Set retained earnings account in General Ledger Setup before year-end close.',
            ]);
        }

        $alreadyClosed = GlEntry::query()
            ->where('is_closing_entry', true)
            ->where('closing_fiscal_year', $fiscalYear)
            ->exists();

        if ($alreadyClosed) {
            throw ValidationException::withMessages([
                'fiscal_year' => "Fiscal year {$fiscalYear} already has closing entries.",
            ]);
        }

        $incomeAccounts = ChartOfAccount::query()
            ->whereIn('account_type', ['REVENUE', 'EXPENSE', 'DIRECT_EXPENSE', 'INDIRECT_EXPENSE', 'OTHER_INCOME', 'OTHER_EXPENSE'])
            ->pluck('id');

        $balances = GlEntry::query()
            ->selectRaw('chart_of_account_id, SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit')
            ->whereYear('posting_date', $fiscalYear)
            ->whereIn('chart_of_account_id', $incomeAccounts)
            ->where('is_closing_entry', false)
            ->groupBy('chart_of_account_id')
            ->get();

        if ($balances->isEmpty()) {
            return ['entries_posted' => 0, 'net_income' => 0.0];
        }

        return DB::transaction(function () use ($balances, $setup, $fiscalYear, $userId): array {
            $transactionNumber = (int) (GlEntry::max('transaction_number') ?? 0) + 1;
            $entryNumber = (int) (GlEntry::max('entry_number') ?? 0) + 1;
            $closingDate = now()->setDate($fiscalYear, 12, 31)->toDateString();

            $netIncome = 0.0;
            $rows = [];

            foreach ($balances as $balance) {
                $net = (float) $balance->total_debit - (float) $balance->total_credit;
                if (abs($net) < 0.0001) {
                    continue;
                }

                $netIncome += -$net;

                $rows[] = [
                    'entry_number' => $entryNumber++,
                    'transaction_number' => $transactionNumber,
                    'chart_of_account_id' => (int) $balance->chart_of_account_id,
                    'debit_amount' => $net < 0 ? abs($net) : 0,
                    'debit_amount_lcy' => $net < 0 ? abs($net) : 0,
                    'credit_amount' => $net > 0 ? abs($net) : 0,
                    'credit_amount_lcy' => $net > 0 ? abs($net) : 0,
                    'amount' => -$net,
                    'amount_lcy' => -$net,
                    'source_type' => 'SYSTEM',
                    'source_number' => 'YEAR_CLOSE',
                    'document_type' => 'CLOSING',
                    'document_number' => "YE-{$fiscalYear}",
                    'document_date' => $closingDate,
                    'posting_date' => $closingDate,
                    'user_id' => $userId,
                    'description' => "Year-end close {$fiscalYear}",
                    'is_closing_entry' => true,
                    'closing_fiscal_year' => $fiscalYear,
                    'reconciled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (abs($netIncome) > 0.0001) {
                $rows[] = [
                    'entry_number' => $entryNumber++,
                    'transaction_number' => $transactionNumber,
                    'chart_of_account_id' => (int) $setup->retained_earnings_account_id,
                    'debit_amount' => $netIncome < 0 ? abs($netIncome) : 0,
                    'debit_amount_lcy' => $netIncome < 0 ? abs($netIncome) : 0,
                    'credit_amount' => $netIncome > 0 ? abs($netIncome) : 0,
                    'credit_amount_lcy' => $netIncome > 0 ? abs($netIncome) : 0,
                    'amount' => $netIncome,
                    'amount_lcy' => $netIncome,
                    'source_type' => 'SYSTEM',
                    'source_number' => 'YEAR_CLOSE',
                    'document_type' => 'CLOSING',
                    'document_number' => "YE-{$fiscalYear}",
                    'document_date' => $closingDate,
                    'posting_date' => $closingDate,
                    'user_id' => $userId,
                    'description' => "Retained earnings close {$fiscalYear}",
                    'is_closing_entry' => true,
                    'closing_fiscal_year' => $fiscalYear,
                    'reconciled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($rows)) {
                GlEntry::insert($rows);
            }

            return [
                'entries_posted' => count($rows),
                'net_income' => round($netIncome, 2),
            ];
        });
    }
}
