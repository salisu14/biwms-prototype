<?php

namespace App\Console\Commands;

use App\Enums\AccountCategory;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingSetup;
use App\Models\ValueEntry;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('biwms:finance-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows}')]
#[Description('Report BIWMS G/L and finance sub-ledger consistency issues.')]
class BiwmsFinanceReconcile extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $report = [
            'gl_debit_credit_imbalances' => $this->glDebitCreditImbalances(),
            'customer_ledger_receivables_mismatches' => $this->customerLedgerReceivablesMismatches(),
            'vendor_ledger_payables_mismatches' => $this->vendorLedgerPayablesMismatches(),
            'bank_ledger_gl_mismatches' => $this->bankLedgerGlMismatches(),
            'inventory_value_gl_mismatches' => $this->inventoryValueGlMismatches(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $details = (bool) $this->option('details');

        $this->info('BIWMS Finance Reconciliation');
        $this->line('Mode: report-only. No G/L or sub-ledger entries were changed.');
        $this->newLine();

        $this->section('G/L debit/credit imbalances', $report['gl_debit_credit_imbalances'], $details, fn (array $entry): string => sprintf(
            '[%s] transaction=%s document=%s %s debit=%s credit=%s difference=%s',
            $entry['severity'],
            $entry['transaction_number'],
            $entry['document_type'],
            $entry['document_number'],
            number_format($entry['debit'], 2, '.', ''),
            number_format($entry['credit'], 2, '.', ''),
            number_format($entry['difference'], 2, '.', ''),
        ));

        $this->section('Customer ledger vs receivables control mismatches', $report['customer_ledger_receivables_mismatches'], $details, fn (array $entry): string => sprintf(
            '[%s] group=%s account=%s subledger=%s gl=%s difference=%s',
            $entry['severity'],
            $entry['posting_group_code'],
            $entry['account_number'],
            number_format($entry['subledger_balance'], 2, '.', ''),
            number_format($entry['gl_balance'], 2, '.', ''),
            number_format($entry['difference'], 2, '.', ''),
        ));

        $this->section('Vendor ledger vs payables control mismatches', $report['vendor_ledger_payables_mismatches'], $details, fn (array $entry): string => sprintf(
            '[%s] group=%s account=%s subledger=%s gl=%s difference=%s',
            $entry['severity'],
            $entry['posting_group_code'],
            $entry['account_number'],
            number_format($entry['subledger_balance'], 2, '.', ''),
            number_format($entry['gl_balance'], 2, '.', ''),
            number_format($entry['difference'], 2, '.', ''),
        ));

        $this->section('Bank ledger vs bank account G/L mismatches', $report['bank_ledger_gl_mismatches'], $details, fn (array $entry): string => sprintf(
            '[%s] bank=%s account=%s bank_ledger=%s gl=%s difference=%s',
            $entry['severity'],
            $entry['bank_account_code'],
            $entry['account_number'],
            number_format($entry['subledger_balance'], 2, '.', ''),
            number_format($entry['gl_balance'], 2, '.', ''),
            number_format($entry['difference'], 2, '.', ''),
        ));

        $this->section('Inventory value entries vs inventory G/L mismatches', $report['inventory_value_gl_mismatches'], $details, fn (array $entry): string => sprintf(
            '[%s] account=%s value_entries=%s gl=%s difference=%s',
            $entry['severity'],
            $entry['account_number'],
            number_format($entry['subledger_balance'], 2, '.', ''),
            number_format($entry['gl_balance'], 2, '.', ''),
            number_format($entry['difference'], 2, '.', ''),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function glDebitCreditImbalances(): array
    {
        return GlEntry::query()
            ->selectRaw('transaction_number, document_type, document_number, COALESCE(SUM(debit_amount), 0) as debit, COALESCE(SUM(credit_amount), 0) as credit')
            ->groupBy('transaction_number', 'document_type', 'document_number')
            ->havingRaw('ABS(COALESCE(SUM(debit_amount), 0) - COALESCE(SUM(credit_amount), 0)) > 0.01')
            ->orderBy('transaction_number')
            ->limit(250)
            ->get()
            ->map(fn ($entry): array => [
                'transaction_number' => $entry->transaction_number,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'debit' => round((float) $entry->debit, 2),
                'credit' => round((float) $entry->credit, 2),
                'difference' => round((float) $entry->debit - (float) $entry->credit, 2),
                ...$this->findingMetadata(
                    classification: 'gl_debit_credit_imbalance',
                    severity: 'critical',
                    suggestedRemediation: 'Review the transaction G/L entries and correct only through an approved journal or reversal; do not edit posted entries directly.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customerLedgerReceivablesMismatches(): array
    {
        return CustomerPostingGroup::query()
            ->with('receivablesAccount:id,account_number,name')
            ->whereNotNull('receivables_account_id')
            ->get()
            ->map(function (CustomerPostingGroup $group): ?array {
                $subledgerBalance = (float) CustomerLedgerEntry::query()
                    ->where('customer_posting_group_id', $group->id)
                    ->where('reversed', false)
                    ->sum(DB::raw('debit_amount - credit_amount'));

                $glBalance = $this->glDebitMinusCredit((int) $group->receivables_account_id);
                $difference = round($subledgerBalance - $glBalance, 2);

                if (abs($difference) < 0.01) {
                    return null;
                }

                return [
                    'posting_group_id' => $group->id,
                    'posting_group_code' => $group->code,
                    'chart_of_account_id' => $group->receivables_account_id,
                    'account_number' => $group->receivablesAccount?->account_number,
                    'subledger_balance' => round($subledgerBalance, 2),
                    'gl_balance' => round($glBalance, 2),
                    'difference' => $difference,
                    ...$this->findingMetadata(
                        classification: 'customer_ledger_receivables_mismatch',
                        severity: 'critical',
                        suggestedRemediation: 'Trace customer ledger entries to the receivables control G/L entries by document number and posting date, then correct through approved posting/reversal paths.'
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vendorLedgerPayablesMismatches(): array
    {
        return VendorPostingGroup::query()
            ->with('payablesAccount:id,account_number,name')
            ->whereNotNull('payables_account_id')
            ->get()
            ->map(function (VendorPostingGroup $group): ?array {
                $subledgerBalance = (float) VendorLedgerEntry::query()
                    ->where('vendor_posting_group_id', $group->id)
                    ->where('reversed', false)
                    ->sum(DB::raw('credit_amount - debit_amount'));

                $glBalance = $this->glCreditMinusDebit((int) $group->payables_account_id);
                $difference = round($subledgerBalance - $glBalance, 2);

                if (abs($difference) < 0.01) {
                    return null;
                }

                return [
                    'posting_group_id' => $group->id,
                    'posting_group_code' => $group->code,
                    'chart_of_account_id' => $group->payables_account_id,
                    'account_number' => $group->payablesAccount?->account_number,
                    'subledger_balance' => round($subledgerBalance, 2),
                    'gl_balance' => round($glBalance, 2),
                    'difference' => $difference,
                    ...$this->findingMetadata(
                        classification: 'vendor_ledger_payables_mismatch',
                        severity: 'critical',
                        suggestedRemediation: 'Trace vendor ledger entries to the payables control G/L entries by document number and posting date, then correct through approved posting/reversal paths.'
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bankLedgerGlMismatches(): array
    {
        return BankAccount::query()
            ->with('glAccount:id,account_number,name')
            ->whereNotNull('gl_account_id')
            ->get()
            ->map(function (BankAccount $bankAccount): ?array {
                $subledgerBalance = (float) BankAccountLedgerEntry::query()
                    ->where('bank_account_id', $bankAccount->id)
                    ->whereNull('voided_at')
                    ->sum('amount');

                $glBalance = $this->glDebitMinusCredit((int) $bankAccount->gl_account_id);
                $difference = round($subledgerBalance - $glBalance, 2);

                if (abs($difference) < 0.01) {
                    return null;
                }

                return [
                    'bank_account_id' => $bankAccount->id,
                    'bank_account_code' => $bankAccount->account_code,
                    'chart_of_account_id' => $bankAccount->gl_account_id,
                    'account_number' => $bankAccount->glAccount?->account_number,
                    'subledger_balance' => round($subledgerBalance, 2),
                    'gl_balance' => round($glBalance, 2),
                    'difference' => $difference,
                    ...$this->findingMetadata(
                        classification: 'bank_ledger_gl_mismatch',
                        severity: 'critical',
                        suggestedRemediation: 'Compare bank account ledger entries to the mapped bank G/L account and correct via bank/payment reversal or approved journal.'
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inventoryValueGlMismatches(): array
    {
        $inventoryAccountIds = InventoryPostingSetup::query()
            ->whereNotNull('inventory_account_id')
            ->pluck('inventory_account_id')
            ->unique()
            ->values();

        if ($inventoryAccountIds->isEmpty()) {
            $inventoryAccountIds = DB::table('chart_of_accounts')
                ->where('account_category', AccountCategory::INVENTORY->value)
                ->pluck('id');
        }

        $subledgerBalance = (float) ValueEntry::query()
            ->sum('cost_amount_actual');

        $glBalance = (float) GlEntry::query()
            ->whereIn('chart_of_account_id', $inventoryAccountIds->all())
            ->sum(DB::raw('debit_amount - credit_amount'));

        $difference = round($subledgerBalance - $glBalance, 2);

        if (abs($difference) < 0.01) {
            return [];
        }

        return [[
            'chart_of_account_ids' => $inventoryAccountIds->all(),
            'account_number' => 'INVENTORY_CONTROL_TOTAL',
            'subledger_balance' => round($subledgerBalance, 2),
            'gl_balance' => round($glBalance, 2),
            'difference' => $difference,
            ...$this->findingMetadata(
                classification: 'inventory_value_gl_mismatch',
                severity: 'critical',
                suggestedRemediation: 'Reconcile Value Entries to inventory G/L by item, posting group, and document number before posting a value adjustment or approved correction.'
            ),
        ]];
    }

    private function glDebitMinusCredit(int $chartOfAccountId): float
    {
        return (float) GlEntry::query()
            ->where('chart_of_account_id', $chartOfAccountId)
            ->sum(DB::raw('debit_amount - credit_amount'));
    }

    private function glCreditMinusDebit(int $chartOfAccountId): float
    {
        return (float) GlEntry::query()
            ->where('chart_of_account_id', $chartOfAccountId)
            ->sum(DB::raw('credit_amount - debit_amount'));
    }

    /**
     * @return array{classification: string, severity: string, suggested_remediation: string}
     */
    private function findingMetadata(string $classification, string $severity, string $suggestedRemediation): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'suggested_remediation' => $suggestedRemediation,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function section(string $title, array $rows, bool $details, callable $formatter): void
    {
        $this->line("{$title}: ".count($rows));

        if ($details) {
            foreach ($rows as $row) {
                $this->line(' - '.$formatter($row));
            }
        }

        $this->newLine();
    }
}
