<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\BankAccountStatementLine;
use App\Models\BankReconciliation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BankReconciliationService
{
    public function __construct(
        private readonly BankAccountLedgerService $ledgerService
    ) {}

    /**
     * Start new reconciliation (BC: Bank Acc. Reconciliation)
     */
    public function startReconciliation(
        BankAccount $bankAccount,
        string $statementNo,
        \DateTime $statementDate,
        float $statementEndingBalance
    ): BankReconciliation {
        return BankReconciliation::create([
            'bank_account_id' => $bankAccount->id,
            'statement_no' => $statementNo,
            'statement_date' => $statementDate,
            'statement_ending_balance' => $statementEndingBalance,
            'bank_balance_at_reconciliation' => $bankAccount->balance,
            'uncleared_deposits' => 0,
            'uncleared_withdrawals' => 0,
            'adjusted_bank_balance' => $bankAccount->balance,
            'reconciled' => false,
        ]);
    }

    /**
     * Import statement lines from file (BC: Import Bank Statement)
     */
    public function importStatement(
        BankAccount $bankAccount,
        string $statementNo,
        array $lines,
        string $format = 'csv'
    ): Collection {
        $imported = collect();

        foreach ($lines as $index => $lineData) {
            $statementLine = BankAccountStatementLine::create([
                'bank_account_id' => $bankAccount->id,
                'statement_no' => $statementNo,
                'statement_line_no' => $index + 1,
                'transaction_date' => $lineData['date'],
                'description' => $lineData['description'],
                'reference_no' => $lineData['reference'] ?? null,
                'statement_amount' => $lineData['amount'],
                'debit_amount' => $lineData['amount'] < 0 ? abs($lineData['amount']) : 0,
                'credit_amount' => $lineData['amount'] > 0 ? $lineData['amount'] : 0,
                'reconciled' => false,
            ]);

            // Auto-match if possible
            $this->autoMatch($statementLine);

            $imported->push($statementLine);
        }

        return $imported;
    }

    /**
     * Auto-match statement line to ledger entries
     */
    public function autoMatch(BankAccountStatementLine $statementLine): ?BankAccountLedgerEntry
    {
        $amount = $statementLine->statement_amount;

        // Try exact amount match within date range
        $match = BankAccountLedgerEntry::forBankAccount($statementLine->bank_account_id)
            ->where('open', true)
            ->whereNull('statement_no')
            ->where('amount', $amount)
            ->whereBetween('posting_date', [
                $statementLine->transaction_date->copy()->subDays(5),
                $statementLine->transaction_date->copy()->addDays(5),
            ])
            ->first();

        if ($match) {
            $match->reconcile($statementLine);

            return $match;
        }

        // Try reference number match
        if ($statementLine->reference_no) {
            $match = BankAccountLedgerEntry::forBankAccount($statementLine->bank_account_id)
                ->where('open', true)
                ->whereNull('statement_no')
                ->where(function ($q) use ($statementLine) {
                    $q->where('document_no', 'like', "%{$statementLine->reference_no}%")
                        ->orWhere('external_document_no', 'like', "%{$statementLine->reference_no}%")
                        ->orWhere('check_no', $statementLine->reference_no);
                })
                ->first();

            if ($match && abs($match->amount - $amount) < 0.01) {
                $match->reconcile($statementLine);

                return $match;
            }
        }

        return null;
    }

    /**
     * Manually match statement line to ledger entry
     */
    public function manualMatch(
        BankAccountStatementLine $statementLine,
        BankAccountLedgerEntry $ledgerEntry
    ): void {
        if (! $ledgerEntry->canReconcile()) {
            throw new \InvalidArgumentException('Ledger entry cannot be reconciled');
        }

        // Calculate difference
        $difference = $statementLine->statement_amount - $ledgerEntry->amount;

        $statementLine->update([
            'difference' => $difference,
        ]);

        $ledgerEntry->reconcile($statementLine);
    }

    /**
     * Suggest matches for unmatched statement line
     */
    public function suggestMatches(BankAccountStatementLine $statementLine, int $limit = 5): Collection
    {
        $amount = $statementLine->statement_amount;
        $date = $statementLine->transaction_date;

        return BankAccountLedgerEntry::forBankAccount($statementLine->bank_account_id)
            ->where('open', true)
            ->whereNull('statement_no')
            ->whereBetween('amount', [$amount * 0.95, $amount * 1.05]) // Within 5%
            ->whereBetween('posting_date', [
                $date->copy()->subDays(10),
                $date->copy()->addDays(10),
            ])
            ->orderByRaw('ABS(amount - ?)', [$amount]) // Closest amount first
            ->limit($limit)
            ->get();
    }

    /**
     * Complete reconciliation
     */
    public function completeReconciliation(BankReconciliation $reconciliation): void
    {
        $bankAccount = $reconciliation->bankAccount;

        // Get uncleared items
        $uncleared = $this->ledgerService->getUnclearedTransactions($bankAccount);

        // Calculate adjusted balance
        $adjustedBalance = $reconciliation->bank_balance_at_reconciliation
            + $uncleared['uncleared_deposits']
            - $uncleared['uncleared_withdrawals'];

        // Verify against statement
        $difference = abs($adjustedBalance - $reconciliation->statement_ending_balance);

        if ($difference > 0.01) {
            throw new \InvalidArgumentException(
                'Reconciliation does not balance. Difference: '.number_format($difference, 2)
            );
        }

        // Update reconciliation
        $reconciliation->update([
            'uncleared_deposits' => $uncleared['uncleared_deposits'],
            'uncleared_withdrawals' => $uncleared['uncleared_withdrawals'],
            'adjusted_bank_balance' => $adjustedBalance,
            'reconciled' => true,
            'reconciled_at' => now(),
            'reconciled_by' => Auth::id(),
        ]);

        // Close reconciled entries
        BankAccountLedgerEntry::forBankAccount($bankAccount->id)
            ->where('status', 'reconciled')
            ->where('statement_no', $reconciliation->statement_no)
            ->update(['open' => false]);

        // Update bank account last reconciliation
        $bankAccount->update([
            'last_statement_no' => $reconciliation->statement_no,
            'last_statement_date' => $reconciliation->statement_date,
        ]);
    }

    /**
     * Get reconciliation report
     */
    public function getReconciliationReport(BankReconciliation $reconciliation): array
    {
        $bankAccount = $reconciliation->bankAccount;

        // Reconciled items
        $reconciledItems = BankAccountLedgerEntry::forBankAccount($bankAccount->id)
            ->where('statement_no', $reconciliation->statement_no)
            ->get();

        // Unreconciled items
        $unreconciledItems = BankAccountLedgerEntry::forBankAccount($bankAccount->id)
            ->where('open', true)
            ->whereNull('statement_no')
            ->get();

        // Unmatched statement lines
        $unmatchedLines = BankAccountStatementLine::forStatement($reconciliation->statement_no)
            ->where('bank_account_id', $bankAccount->id)
            ->where('reconciled', false)
            ->get();

        return [
            'reconciliation' => $reconciliation,
            'reconciled_items' => $reconciledItems,
            'unreconciled_items' => $unreconciledItems,
            'unmatched_statement_lines' => $unmatchedLines,
            'summary' => [
                'statement_balance' => $reconciliation->statement_ending_balance,
                'book_balance' => $reconciliation->bank_balance_at_reconciliation,
                'reconciled_amount' => $reconciledItems->sum('amount'),
                'difference' => $reconciliation->statement_ending_balance - $reconciliation->adjusted_bank_balance,
            ],
        ];
    }
}
