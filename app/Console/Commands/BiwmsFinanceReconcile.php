<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AccountCategory;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingSetup;
use App\Models\Payment;
use App\Models\PostingTransaction;
use App\Models\ValueEntry;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:finance-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows} {--export= : Write the JSON report to a file path}')]
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
            'customer_ledger_missing_posting_groups' => $this->customerLedgerMissingPostingGroups(),
            'legacy_monetary_credit_memo_application_entries' => $this->legacyMonetaryCreditMemoApplicationEntries(),
            'customer_ledger_receivables_mismatches' => $this->customerLedgerReceivablesMismatches(),
            'vendor_ledger_payables_mismatches' => $this->vendorLedgerPayablesMismatches(),
            'bank_ledger_gl_mismatches' => $this->bankLedgerGlMismatches(),
            'valid_bank_opening_balance_corrections' => $this->validBankOpeningBalanceCorrections(),
            'invalid_bank_opening_balance_corrections' => $this->invalidBankOpeningBalanceCorrections(),
            'inventory_value_gl_mismatches' => $this->inventoryValueGlMismatches(),
            'missing_control_account_entries' => $this->missingControlAccountEntries(),
            'value_entries_gl_posted_without_posting_transaction' => $this->valueEntriesGlPostedWithoutPostingTransaction(),
            'value_entry_posting_transactions_without_value_entry' => $this->valueEntryPostingTransactionsWithoutValueEntry(),
            'duplicate_gl_posting_for_value_entry' => $this->duplicateGlPostingForValueEntry(),
            'source_and_value_entry_duplicate_inventory_value' => $this->sourceAndValueEntryDuplicateInventoryValue(),
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $details = (bool) $this->option('details');

        $this->info('BIWMS Finance Reconciliation');
        $this->line('Mode: report-only. No G/L or sub-ledger entries were changed.');
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
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

        $this->section('Customer ledger entries missing posting-group metadata', $report['customer_ledger_missing_posting_groups'], $details, fn (array $entry): string => sprintf(
            '[%s] entry=%s customer=%s document=%s %s expected_customer_group=%s expected_business_group=%s ledger_customer_group=%s ledger_business_group=%s',
            $entry['severity'],
            $entry['entry_id'],
            $entry['customer_id'],
            $entry['document_type'],
            $entry['document_number'],
            $entry['expected_customer_posting_group_id'],
            $entry['expected_general_business_posting_group_id'],
            $entry['ledger_customer_posting_group_id'] ?? 'null',
            $entry['ledger_general_business_posting_group_id'] ?? 'null',
        ));

        $this->section('Legacy monetary credit memo application ledger entries', $report['legacy_monetary_credit_memo_application_entries'], $details, fn (array $entry): string => sprintf(
            '[%s] entry=%s customer=%s document=%s amount=%s debit=%s credit=%s',
            $entry['severity'],
            $entry['entry_id'],
            $entry['customer_id'],
            $entry['document_number'],
            number_format($entry['amount'], 2, '.', ''),
            number_format($entry['debit_amount'], 2, '.', ''),
            number_format($entry['credit_amount'], 2, '.', ''),
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

        $this->section('Valid bank opening-balance corrections', $report['valid_bank_opening_balance_corrections'], $details, fn (array $entry): string => sprintf(
            '[%s] %s %s amount=%s original_tx=%s correction_tx=%s',
            $entry['severity'],
            $entry['bank_account_code'],
            $entry['document_number'],
            number_format($entry['amount'], 2, '.', ''),
            $entry['original_transaction_id'],
            $entry['correction_transaction_id'],
        ));

        $this->section('Invalid bank opening-balance corrections', $report['invalid_bank_opening_balance_corrections'], $details, fn (array $entry): string => sprintf(
            '[%s] %s %s transaction=%s reason=%s',
            $entry['severity'],
            $entry['document_type'],
            $entry['document_number'],
            $entry['posting_transaction_id'],
            $entry['reason'],
        ));

        $this->section('Inventory value entries vs inventory G/L mismatches', $report['inventory_value_gl_mismatches'], $details, fn (array $entry): string => sprintf(
            '[%s] account=%s value_entries=%s gl=%s difference=%s',
            $entry['severity'],
            $entry['account_number'],
            number_format($entry['subledger_balance'], 2, '.', ''),
            number_format($entry['gl_balance'], 2, '.', ''),
            number_format($entry['difference'], 2, '.', ''),
        ));

        $this->section('Missing control account entries', $report['missing_control_account_entries'], $details, fn (array $entry): string => sprintf(
            '[%s] %s %s %s account=%s amount=%s source=%s',
            $entry['severity'],
            $entry['control_type'],
            $entry['document_type'],
            $entry['document_number'],
            $entry['account_number'],
            number_format($entry['amount'], 2, '.', ''),
            $entry['source_hint'],
        ));

        $this->section('Value Entries marked G/L posted without PostingTransaction', $report['value_entries_gl_posted_without_posting_transaction'], $details, fn (array $entry): string => sprintf(
            '[%s] value_entry=%s document=%s %s item=%s',
            $entry['severity'],
            $entry['value_entry_no'],
            $entry['document_type'],
            $entry['document_number'],
            $entry['item_no'],
        ));

        $this->section('Value Entry PostingTransactions without related ValueEntry', $report['value_entry_posting_transactions_without_value_entry'], $details, fn (array $entry): string => sprintf(
            '[%s] transaction=%s document=%s %s key=%s',
            $entry['severity'],
            $entry['posting_transaction_id'],
            $entry['document_type'],
            $entry['document_number'],
            $entry['transaction_key'],
        ));

        $this->section('Duplicate G/L posting for Value Entries', $report['duplicate_gl_posting_for_value_entry'], $details, fn (array $entry): string => sprintf(
            '[%s] value_entry=%s transaction=%s document=%s %s gl_lines=%s',
            $entry['severity'],
            $entry['value_entry_no'],
            $entry['posting_transaction_id'],
            $entry['document_type'],
            $entry['document_number'],
            $entry['gl_line_count'],
        ));

        $this->section('Source service and Value Entry duplicate inventory value postings', $report['source_and_value_entry_duplicate_inventory_value'], $details, fn (array $entry): string => sprintf(
            '[%s] value_entry=%s document=%s %s item_ledger_entry_id=%s duplicate_gl_lines=%s',
            $entry['severity'],
            $entry['value_entry_no'],
            $entry['document_type'],
            $entry['document_number'],
            $entry['item_ledger_entry_id'],
            $entry['duplicate_gl_line_count'],
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
                    classification: 'gl_imbalance',
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
    private function customerLedgerMissingPostingGroups(): array
    {
        return CustomerLedgerEntry::query()
            ->join('customers', 'customer_ledger_entries.customer_id', '=', 'customers.id')
            ->where('customer_ledger_entries.reversed', false)
            ->where('customer_ledger_entries.source_type', Payment::class)
            ->whereNotNull('customers.customer_posting_group_id')
            ->whereNotNull('customers.general_business_posting_group_id')
            ->where(function ($query): void {
                $query
                    ->whereNull('customer_ledger_entries.customer_posting_group_id')
                    ->orWhereNull('customer_ledger_entries.general_business_posting_group_id')
                    ->orWhereColumn('customer_ledger_entries.customer_posting_group_id', '!=', 'customers.customer_posting_group_id')
                    ->orWhereColumn('customer_ledger_entries.general_business_posting_group_id', '!=', 'customers.general_business_posting_group_id');
            })
            ->orderBy('customer_ledger_entries.id')
            ->limit(250)
            ->get([
                'customer_ledger_entries.id',
                'customer_ledger_entries.customer_id',
                'customer_ledger_entries.document_type',
                'customer_ledger_entries.document_number',
                'customer_ledger_entries.customer_posting_group_id',
                'customer_ledger_entries.general_business_posting_group_id',
                'customers.customer_posting_group_id as expected_customer_posting_group_id',
                'customers.general_business_posting_group_id as expected_general_business_posting_group_id',
            ])
            ->map(fn ($entry): array => [
                'entry_id' => $entry->id,
                'customer_id' => $entry->customer_id,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'ledger_customer_posting_group_id' => $entry->customer_posting_group_id,
                'ledger_general_business_posting_group_id' => $entry->general_business_posting_group_id,
                'expected_customer_posting_group_id' => $entry->expected_customer_posting_group_id,
                'expected_general_business_posting_group_id' => $entry->expected_general_business_posting_group_id,
                ...$this->findingMetadata(
                    classification: 'customer_ledger_missing_posting_group',
                    severity: 'warning',
                    suggestedRemediation: 'Review the payment-created customer ledger entry, then run php artisan biwms:customer-ledger-posting-groups-repair --dry-run and apply only after approval.'
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function legacyMonetaryCreditMemoApplicationEntries(): array
    {
        return CustomerLedgerEntry::query()
            ->where('document_type', 'CREDIT_MEMO_APPLICATION')
            ->where(function ($query): void {
                $query
                    ->whereRaw('ABS(COALESCE(amount, 0)) > 0.01')
                    ->orWhereRaw('ABS(COALESCE(debit_amount, 0)) > 0.01')
                    ->orWhereRaw('ABS(COALESCE(credit_amount, 0)) > 0.01');
            })
            ->orderBy('id')
            ->limit(250)
            ->get([
                'id',
                'customer_id',
                'document_number',
                'amount',
                'debit_amount',
                'credit_amount',
                'posting_date',
            ])
            ->map(fn (CustomerLedgerEntry $entry): array => [
                'entry_id' => $entry->id,
                'customer_id' => $entry->customer_id,
                'document_number' => $entry->document_number,
                'posting_date' => optional($entry->posting_date)->toDateString(),
                'amount' => round((float) $entry->amount, 2),
                'debit_amount' => round((float) $entry->debit_amount, 2),
                'credit_amount' => round((float) $entry->credit_amount, 2),
                ...$this->findingMetadata(
                    classification: 'legacy_monetary_credit_memo_application_entry',
                    severity: 'warning',
                    suggestedRemediation: 'Review this historical CREDIT_MEMO_APPLICATION customer ledger row. Credit memo applications should be represented by customer_ledger_applications, not by monetary CustomerLedgerEntry rows. Do not delete or rewrite posted ledger data without an approved manual remediation plan.'
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
            ->whereNotNull('receivables_account_id')
            ->select('receivables_account_id')
            ->distinct()
            ->with('receivablesAccount:id,account_number,name')
            ->get()
            ->map(function (CustomerPostingGroup $group): ?array {
                $groupIds = CustomerPostingGroup::query()
                    ->where('receivables_account_id', $group->receivables_account_id)
                    ->pluck('id');
                $subledgerBalance = (float) CustomerLedgerEntry::query()
                    ->whereIn('customer_posting_group_id', $groupIds)
                    ->sum(DB::raw('debit_amount - credit_amount'));

                $glBalance = $this->glDebitMinusCredit((int) $group->receivables_account_id);
                $difference = round($subledgerBalance - $glBalance, 2);

                if (abs($difference) < 0.01) {
                    return null;
                }

                return [
                    'posting_group_id' => $group->id,
                    'posting_group_code' => CustomerPostingGroup::query()
                        ->whereIn('id', $groupIds)
                        ->orderBy('code')
                        ->pluck('code')
                        ->implode(', '),
                    'chart_of_account_id' => $group->receivables_account_id,
                    'account_number' => $group->receivablesAccount?->account_number,
                    'subledger_balance' => round($subledgerBalance, 2),
                    'gl_balance' => round($glBalance, 2),
                    'difference' => $difference,
                    ...$this->findingMetadata(
                        classification: 'customer_ledger_gl_mismatch',
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
                        classification: 'vendor_ledger_gl_mismatch',
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
        $inventoryAccountIds = $this->valueEntryControlAccountIds();

        if ($inventoryAccountIds->isEmpty()) {
            return [];
        }

        $legacyDocumentNumbers = ValueEntry::query()
            ->whereNull('posting_transaction_id')
            ->whereNotNull('document_no')
            ->pluck('document_no')
            ->unique()
            ->values();

        if ($legacyDocumentNumbers->isEmpty()) {
            return [];
        }

        $subledgerBalance = (float) ValueEntry::query()
            ->whereNull('posting_transaction_id')
            ->where(function ($query): void {
                $query->where('expected_cost', false)
                    ->orWhereNull('expected_cost');
            })
            ->selectRaw($this->inventoryValueEffectSql(alias: 'inventory_value_effect'))
            ->value('inventory_value_effect');

        $glBalance = (float) GlEntry::query()
            ->whereIn('chart_of_account_id', $inventoryAccountIds->all())
            ->whereIn('document_number', $legacyDocumentNumbers->all())
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
            'diagnostic_hint' => 'Compared legacy Value Entries without PostingTransaction metadata against InventoryPostingSetup inventory, WIP, in-transit, and interim control accounts. Modern Value Entries are reconciled by posting_transaction_id diagnostics.',
            ...$this->findingMetadata(
                classification: 'inventory_value_gl_mismatch',
                severity: 'critical',
                suggestedRemediation: 'Reconcile Value Entries to inventory G/L by item, posting group, and document number before posting a value adjustment or approved correction.'
            ),
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missingControlAccountEntries(): array
    {
        return collect()
            ->merge($this->bankGlEntriesMissingBankLedger())
            ->merge($this->customerLedgerEntriesMissingReceivablesGl())
            ->merge($this->vendorLedgerEntriesMissingPayablesGl())
            ->merge($this->valueEntriesMissingInventoryGl())
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bankGlEntriesMissingBankLedger(): array
    {
        return DB::table('gl_entries as gl')
            ->join('bank_accounts as bank', 'bank.gl_account_id', '=', 'gl.chart_of_account_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'gl.chart_of_account_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('bank_account_ledger_entries as bale')
                    ->whereColumn('bale.bank_account_id', 'bank.id')
                    ->whereColumn('bale.document_no', 'gl.document_number')
                    ->whereNull('bale.deleted_at');
            })
            ->groupBy('bank.id', 'bank.account_code', 'coa.account_number', 'gl.document_type', 'gl.document_number', 'gl.sourceable_type')
            ->orderBy('gl.document_number')
            ->limit(250)
            ->get([
                'bank.id as bank_account_id',
                'bank.account_code as bank_account_code',
                'coa.account_number',
                'gl.document_type',
                'gl.document_number',
                'gl.sourceable_type',
                DB::raw('COALESCE(SUM(gl.debit_amount - gl.credit_amount), 0) as amount'),
            ])
            ->reject(fn ($entry): bool => $entry->document_type === 'BANK_OPENING_CORRECTION'
                && $this->validBankOpeningBalanceCorrection((string) $entry->document_number) !== null)
            ->map(fn ($entry): array => [
                'control_type' => 'BANK',
                'bank_account_id' => $entry->bank_account_id,
                'bank_account_code' => $entry->bank_account_code,
                'account_number' => $entry->account_number,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'amount' => round((float) $entry->amount, 2),
                'source_hint' => $entry->sourceable_type ?: 'G/L entry',
                ...$this->findingMetadata(
                    classification: 'missing_control_account_entry',
                    severity: 'critical',
                    suggestedRemediation: 'This bank G/L control entry has no matching Bank Account Ledger Entry for the same bank account and document number. Review the posting path and correct only through an approved reversal/repost or controlled remediation plan.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * Return only corrections that are proven to be G/L-only reclassifications.
     * Invalid correction-shaped rows remain visible through the ordinary bank
     * control diagnostic above.
     *
     * @return array<int, array<string, mixed>>
     */
    private function validBankOpeningBalanceCorrections(): array
    {
        return PostingTransaction::query()
            ->where('document_type', 'BANK_OPENING_CORRECTION')
            ->where('status', 'completed')
            ->whereNotNull('reversal_of_transaction_id')
            ->whereNotNull('idempotency_key')
            ->orderBy('id')
            ->get()
            ->map(fn (PostingTransaction $transaction): ?array => $this->validBankOpeningBalanceCorrection($transaction->document_number))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Correction-shaped transactions are surfaced when they fail validation,
     * even if the ordinary bank-control query cannot identify their account.
     *
     * @return array<int, array<string, mixed>>
     */
    private function invalidBankOpeningBalanceCorrections(): array
    {
        return PostingTransaction::query()
            ->where('document_type', 'BANK_OPENING_CORRECTION')
            ->orderBy('id')
            ->get()
            ->reject(fn (PostingTransaction $transaction): bool => $this->validBankOpeningBalanceCorrection($transaction->document_number) !== null)
            ->map(fn (PostingTransaction $transaction): array => [
                'severity' => 'critical',
                'classification' => 'invalid_bank_opening_balance_correction',
                'posting_transaction_id' => $transaction->id,
                'document_type' => $transaction->document_type,
                'document_number' => $transaction->document_number,
                'reason' => 'One or more bank opening-balance correction invariants failed.',
                ...$this->findingMetadata(
                    classification: 'invalid_bank_opening_balance_correction',
                    severity: 'critical',
                    suggestedRemediation: 'Trace the correction transaction, original opening balance, bank account, and Opening Balance Equity account. Do not create a second bank ledger entry or edit posted history directly.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validBankOpeningBalanceCorrection(string $documentNumber): ?array
    {
        $transaction = PostingTransaction::query()
            ->where('document_type', 'BANK_OPENING_CORRECTION')
            ->where('document_number', $documentNumber)
            ->where('status', 'completed')
            ->whereNotNull('reversal_of_transaction_id')
            ->whereNotNull('idempotency_key')
            ->with('glEntries')
            ->first();

        if (! $transaction) {
            return null;
        }

        $original = PostingTransaction::query()->find($transaction->reversal_of_transaction_id);
        $bank = $original?->source_type === 'BANK'
            ? BankAccount::query()->find($original->source_id)
            : null;

        if (! $original || $original->document_type !== 'OPENING_BALANCE' || ! $bank) {
            return null;
        }

        $originalLedger = BankAccountLedgerEntry::query()
            ->where('bank_account_id', $bank->id)
            ->where('document_type', 'OPENING_BALANCE')
            ->where('document_no', $original->document_number)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->first();
        $setup = DB::table('general_ledger_setup')->first();
        $lines = $transaction->glEntries;
        $amount = $originalLedger ? round((float) $originalLedger->amount, 2) : 0.0;
        $debitLines = $lines->filter(fn (GlEntry $line): bool => (float) $line->debit_amount > 0 && (float) $line->credit_amount == 0);
        $creditLines = $lines->filter(fn (GlEntry $line): bool => (float) $line->credit_amount > 0 && (float) $line->debit_amount == 0);
        $bankDebit = $debitLines->firstWhere('chart_of_account_id', $bank->gl_account_id);
        $equityCredit = $setup?->opening_balance_equity_account_id
            ? $creditLines->firstWhere('chart_of_account_id', $setup->opening_balance_equity_account_id)
            : null;
        $expectedKey = 'BANK_OPENING_CORRECTION:BANK:'.$bank->id.':ORIGINAL_TX:'.$original->id;
        $sameKeyCount = PostingTransaction::query()
            ->where('document_type', 'BANK_OPENING_CORRECTION')
            ->where(function ($query) use ($expectedKey): void {
                $query->where('transaction_key', $expectedKey)
                    ->orWhere('idempotency_key', $expectedKey);
            })
            ->count();
        $correctionLedgerExists = BankAccountLedgerEntry::query()
            ->where('bank_account_id', $bank->id)
            ->where('document_no', $transaction->document_number)
            ->whereNull('deleted_at')
            ->exists();

        if (
            ! $originalLedger
            || $transaction->reversal_of_transaction_id !== $original->id
            || $transaction->transaction_key !== $expectedKey
            || $transaction->idempotency_key !== $expectedKey
            || $sameKeyCount !== 1
            || $transaction->business_id !== $original->business_id
            || $lines->count() !== 2
            || $debitLines->count() !== 1
            || $creditLines->count() !== 1
            || ! $bankDebit
            || ! $equityCredit
            || ! $setup?->opening_balance_equity_account_id
            || (int) $setup->opening_balance_equity_account_id === (int) $bank->gl_account_id
            || abs((float) $bankDebit->debit_amount - $amount) > 0.01
            || abs((float) $equityCredit->credit_amount - $amount) > 0.01
            || abs((float) $debitLines->sum('debit_amount') - (float) $creditLines->sum('credit_amount')) > 0.01
            || $correctionLedgerExists
        ) {
            return null;
        }

        return [
            'severity' => 'info',
            'classification' => 'valid_bank_opening_balance_correction',
            'bank_account_id' => $bank->id,
            'bank_account_code' => $bank->account_code,
            'document_type' => $transaction->document_type,
            'document_number' => $transaction->document_number,
            'amount' => $amount,
            'original_transaction_id' => $original->id,
            'correction_transaction_id' => $transaction->id,
            'suggested_remediation' => 'No remediation required; this is a validated G/L-only bank opening-balance reclassification with no additional cash movement.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customerLedgerEntriesMissingReceivablesGl(): array
    {
        return DB::table('customer_ledger_entries as cle')
            ->join('customer_posting_groups as cpg', 'cpg.id', '=', 'cle.customer_posting_group_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'cpg.receivables_account_id')
            ->where('cle.reversed', false)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('gl_entries as gl')
                    ->where(function ($query): void {
                        $query
                            ->whereColumn('gl.chart_of_account_id', 'cpg.receivables_account_id')
                            ->whereColumn('gl.document_number', 'cle.document_number')
                            ->orWhere(function ($query): void {
                                $query
                                    ->where('gl.document_type', 'CUSTOMER_CTRL_RECLASS')
                                    ->whereColumn('gl.external_document_number', 'cle.document_number');
                            });
                    });
            })
            ->groupBy('cle.document_type', 'cle.document_number', 'coa.account_number')
            ->orderBy('cle.document_number')
            ->limit(250)
            ->get([
                'cle.document_type',
                'cle.document_number',
                'coa.account_number',
                DB::raw('COALESCE(SUM(cle.debit_amount - cle.credit_amount), 0) as amount'),
            ])
            ->map(fn ($entry): array => [
                'control_type' => 'CUSTOMER',
                'account_number' => $entry->account_number,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'amount' => round((float) $entry->amount, 2),
                'source_hint' => 'Customer Ledger Entry',
                ...$this->findingMetadata(
                    classification: 'missing_control_account_entry',
                    severity: 'critical',
                    suggestedRemediation: 'This customer ledger entry has no matching receivables G/L control entry. Trace the source posting and correct through an approved repost/reversal path.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vendorLedgerEntriesMissingPayablesGl(): array
    {
        return DB::table('vendor_ledger_entries as vle')
            ->join('vendor_posting_groups as vpg', 'vpg.id', '=', 'vle.vendor_posting_group_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'vpg.payables_account_id')
            ->where('vle.reversed', false)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('gl_entries as gl')
                    ->whereColumn('gl.chart_of_account_id', 'vpg.payables_account_id')
                    ->whereColumn('gl.document_number', 'vle.document_number');
            })
            ->groupBy('vle.document_type', 'vle.document_number', 'coa.account_number')
            ->orderBy('vle.document_number')
            ->limit(250)
            ->get([
                'vle.document_type',
                'vle.document_number',
                'coa.account_number',
                DB::raw('COALESCE(SUM(vle.credit_amount - vle.debit_amount), 0) as amount'),
            ])
            ->map(fn ($entry): array => [
                'control_type' => 'VENDOR',
                'account_number' => $entry->account_number,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'amount' => round((float) $entry->amount, 2),
                'source_hint' => 'Vendor Ledger Entry',
                ...$this->findingMetadata(
                    classification: 'missing_control_account_entry',
                    severity: 'critical',
                    suggestedRemediation: 'This vendor ledger entry has no matching payables G/L control entry. Trace the source posting and correct through an approved repost/reversal path.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function valueEntriesMissingInventoryGl(): array
    {
        $controlAccountIds = $this->valueEntryControlAccountIds();

        if ($controlAccountIds->isEmpty()) {
            return [];
        }

        $modernRows = DB::table('value_entries as ve')
            ->where('ve.gl_posted', true)
            ->whereNotNull('ve.posting_transaction_id')
            ->whereNotExists(function ($query) use ($controlAccountIds): void {
                $query->selectRaw('1')
                    ->from('gl_entries as gl')
                    ->whereColumn('gl.posting_transaction_id', 've.posting_transaction_id')
                    ->whereIn('gl.chart_of_account_id', $controlAccountIds->all());
            })
            ->orderBy('ve.entry_no')
            ->limit(250)
            ->get([
                've.entry_no',
                've.posting_transaction_id',
                've.document_type',
                've.document_no as document_number',
                've.item_no',
                've.item_ledger_entry_no',
                DB::raw('COALESCE(ve.cost_amount_actual, 0) as amount'),
            ])
            ->map(fn ($entry): array => [
                'control_type' => 'INVENTORY',
                'account_number' => 'VALUE_ENTRY_CONTROL_TRANSACTION',
                'value_entry_no' => $entry->entry_no,
                'posting_transaction_id' => $entry->posting_transaction_id,
                'item_no' => $entry->item_no,
                'item_ledger_entry_no' => $entry->item_ledger_entry_no,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'amount' => round((float) $entry->amount, 2),
                'source_hint' => 'Value Entry PostingTransaction',
                ...$this->findingMetadata(
                    classification: 'missing_control_account_entry',
                    severity: 'critical',
                    suggestedRemediation: 'This modern Value Entry is marked G/L posted but its PostingTransaction has no inventory/WIP control account line. Review the Value Entry PostingTransaction before planning a controlled correction.'
                ),
            ]);

        $legacyRows = DB::table('value_entries as ve')
            ->whereNotNull('ve.document_no')
            ->whereNull('ve.posting_transaction_id')
            ->whereNotExists(function ($query) use ($controlAccountIds): void {
                $query->selectRaw('1')
                    ->from('gl_entries as gl')
                    ->whereIn('gl.chart_of_account_id', $controlAccountIds->all())
                    ->where(function ($fallback): void {
                        $fallback
                            ->whereColumn('gl.document_number', 've.document_no')
                            ->orWhereExists(function ($itemLedger): void {
                                $itemLedger->selectRaw('1')
                                    ->from('item_ledger_entries as ile')
                                    ->whereColumn('ile.entry_number', 've.item_ledger_entry_no')
                                    ->whereColumn('gl.item_ledger_entry_id', 'ile.id');
                            });
                    });
            })
            ->groupBy('ve.entry_no', 've.document_type', 've.document_no', 've.item_no', 've.item_ledger_entry_no')
            ->orderBy('ve.document_no')
            ->limit(250)
            ->get([
                've.entry_no',
                've.document_type',
                've.document_no as document_number',
                've.item_no',
                've.item_ledger_entry_no',
                DB::raw($this->inventoryValueEffectSql('ve', 'amount')),
            ])
            ->map(function ($entry): array {
                $amount = round((float) $entry->amount, 2);
                $hasMonetaryEffect = abs($amount) > 0.01;

                return [
                    'control_type' => 'INVENTORY',
                    'account_number' => 'LEGACY_INVENTORY_CONTROL_TOTAL',
                    'value_entry_no' => $entry->entry_no,
                    'posting_transaction_id' => null,
                    'item_no' => $entry->item_no,
                    'item_ledger_entry_no' => $entry->item_ledger_entry_no,
                    'document_type' => $entry->document_type,
                    'document_number' => $entry->document_number,
                    'amount' => $amount,
                    'source_hint' => 'Legacy Value Entry document fallback',
                    ...$this->findingMetadata(
                        classification: 'missing_control_account_entry',
                        severity: $hasMonetaryEffect ? 'critical' : 'info',
                        suggestedRemediation: $hasMonetaryEffect
                            ? 'This legacy Value Entry has no matching inventory/WIP G/L control entry by document or item ledger metadata. Review source posting before planning a controlled correction.'
                            : 'Legacy metadata is incomplete, but this row has no monetary inventory effect. Retain it for historical traceability and do not repair automatically.'
                    ),
                ];
            });

        return $modernRows
            ->merge($legacyRows)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function valueEntriesGlPostedWithoutPostingTransaction(): array
    {
        if (! $this->valueEntryPostingMetadataExists()) {
            return [];
        }

        return ValueEntry::query()
            ->where('gl_posted', true)
            ->whereNull('posting_transaction_id')
            ->orderBy('entry_no')
            ->limit(250)
            ->get(['entry_no', 'document_type', 'document_no', 'item_no'])
            ->map(fn (ValueEntry $entry): array => [
                'value_entry_no' => $entry->entry_no,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_no,
                'item_no' => $entry->item_no,
                ...$this->findingMetadata(
                    classification: 'value_entry_gl_posted_without_posting_transaction',
                    severity: 'critical',
                    suggestedRemediation: 'Trace the historical Value Entry and its G/L lines. Future postings must use ValueEntryAccountingOrchestrator; correct legacy rows only through an approved remediation plan.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function valueEntryPostingTransactionsWithoutValueEntry(): array
    {
        if (! $this->valueEntryPostingMetadataExists()) {
            return [];
        }

        return DB::table('posting_transactions as pt')
            ->where('pt.transaction_key', 'like', 'value-entry:%')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('value_entries as ve')
                    ->whereColumn('ve.posting_transaction_id', 'pt.id');
            })
            ->orderBy('pt.id')
            ->limit(250)
            ->get(['pt.id', 'pt.document_type', 'pt.document_number', 'pt.transaction_key'])
            ->map(fn ($entry): array => [
                'posting_transaction_id' => $entry->id,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'transaction_key' => $entry->transaction_key,
                ...$this->findingMetadata(
                    classification: 'posting_transaction_without_value_entry',
                    severity: 'critical',
                    suggestedRemediation: 'Review the PostingTransaction and related G/L entries. Value-entry-owned transactions must remain linked to exactly one Value Entry.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function duplicateGlPostingForValueEntry(): array
    {
        if (! $this->valueEntryPostingMetadataExists()) {
            return [];
        }

        return DB::table('value_entries as ve')
            ->join('gl_entries as gl', 'gl.posting_transaction_id', '=', 've.posting_transaction_id')
            ->whereNotNull('ve.posting_transaction_id')
            ->groupBy('ve.entry_no', 've.posting_transaction_id', 've.document_type', 've.document_no')
            ->havingRaw('COUNT(gl.id) > 2')
            ->orderBy('ve.entry_no')
            ->limit(250)
            ->get([
                've.entry_no',
                've.posting_transaction_id',
                've.document_type',
                've.document_no',
                DB::raw('COUNT(gl.id) as gl_line_count'),
            ])
            ->map(fn ($entry): array => [
                'value_entry_no' => $entry->entry_no,
                'posting_transaction_id' => $entry->posting_transaction_id,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_no,
                'gl_line_count' => (int) $entry->gl_line_count,
                ...$this->findingMetadata(
                    classification: 'duplicate_gl_posting_for_value_entry',
                    severity: 'critical',
                    suggestedRemediation: 'A normal value-entry posting should produce one balanced two-line PostingTransaction. Review duplicated G/L lines and correct through approved reversal/remediation.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sourceAndValueEntryDuplicateInventoryValue(): array
    {
        if (! $this->valueEntryPostingMetadataExists()) {
            return [];
        }

        return DB::table('value_entries as ve')
            ->join('posting_transactions as owner_pt', 'owner_pt.id', '=', 've.posting_transaction_id')
            ->join('posting_transactions as duplicate_pt', function ($join): void {
                $join
                    ->whereColumn('duplicate_pt.id', '<>', 've.posting_transaction_id')
                    ->where(function ($keys): void {
                        $keys
                            ->whereColumn('duplicate_pt.transaction_key', 'owner_pt.transaction_key')
                            ->orWhereColumn('duplicate_pt.idempotency_key', 'owner_pt.idempotency_key')
                            ->orWhereRaw("duplicate_pt.transaction_key = 'value-entry:' || ve.entry_no");
                    });
            })
            ->where('ve.gl_posted', true)
            ->whereNotNull('ve.posting_transaction_id')
            ->where(function ($query): void {
                $query
                    ->where('owner_pt.transaction_key', 'like', 'value-entry:%')
                    ->orWhereRaw("owner_pt.transaction_key = 'value-entry:' || ve.entry_no");
            })
            ->groupBy('ve.entry_no', 've.posting_transaction_id', 've.document_type', 've.document_no')
            ->orderBy('ve.entry_no')
            ->limit(250)
            ->get([
                've.entry_no',
                've.posting_transaction_id',
                've.document_type',
                've.document_no',
                DB::raw('COUNT(DISTINCT duplicate_pt.id) as duplicate_transaction_count'),
            ])
            ->map(fn ($entry): array => [
                'value_entry_no' => $entry->entry_no,
                'posting_transaction_id' => $entry->posting_transaction_id,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_no,
                'duplicate_transaction_count' => (int) $entry->duplicate_transaction_count,
                ...$this->findingMetadata(
                    classification: 'source_and_value_entry_duplicate_inventory_value',
                    severity: 'critical',
                    suggestedRemediation: 'More than one PostingTransaction appears to claim the same Value Entry economic event. Review transaction_key/idempotency_key ownership before planning a controlled remediation.'
                ),
            ])
            ->values()
            ->all();
    }

    private function valueEntryPostingMetadataExists(): bool
    {
        return Schema::hasTable('value_entries')
            && Schema::hasTable('posting_transactions')
            && Schema::hasColumn('value_entries', 'posting_transaction_id')
            && Schema::hasColumn('gl_entries', 'posting_transaction_id');
    }

    private function glDebitMinusCredit(int $chartOfAccountId): float
    {
        return (float) GlEntry::query()
            ->where('chart_of_account_id', $chartOfAccountId)
            ->sum(DB::raw('debit_amount - credit_amount'));
    }

    private function inventoryValueEffectSql(string $table = 'value_entries', string $alias = 'inventory_value_effect'): string
    {
        return "COALESCE(SUM({$table}.cost_amount_actual), 0) as {$alias}";
    }

    /**
     * @return Collection<int, int>
     */
    private function valueEntryControlAccountIds(): Collection
    {
        $accountIds = collect([
            ...InventoryPostingSetup::query()->whereNotNull('inventory_account_id')->pluck('inventory_account_id')->all(),
            ...InventoryPostingSetup::query()->whereNotNull('inventory_account_interim_id')->pluck('inventory_account_interim_id')->all(),
            ...InventoryPostingSetup::query()->whereNotNull('inventory_in_transit_account_id')->pluck('inventory_in_transit_account_id')->all(),
            ...InventoryPostingSetup::query()->whereNotNull('wip_account_id')->pluck('wip_account_id')->all(),
        ])
            ->filter()
            ->unique()
            ->values();

        if ($accountIds->isNotEmpty()) {
            return $accountIds;
        }

        return DB::table('chart_of_accounts')
            ->where('account_category', AccountCategory::INVENTORY->value)
            ->pluck('id')
            ->unique()
            ->values();
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

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $report
     */
    private function exportReport(array $report, string $path): void
    {
        $absolutePath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}
