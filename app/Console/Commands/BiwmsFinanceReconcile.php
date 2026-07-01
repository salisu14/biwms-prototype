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
use Illuminate\Support\Facades\File;

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
            'customer_ledger_receivables_mismatches' => $this->customerLedgerReceivablesMismatches(),
            'vendor_ledger_payables_mismatches' => $this->vendorLedgerPayablesMismatches(),
            'bank_ledger_gl_mismatches' => $this->bankLedgerGlMismatches(),
            'inventory_value_gl_mismatches' => $this->inventoryValueGlMismatches(),
            'missing_control_account_entries' => $this->missingControlAccountEntries(),
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
            ->selectRaw($this->inventoryValueEffectSql(alias: 'inventory_value_effect'))
            ->value('inventory_value_effect');

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
                    ->whereColumn('gl.chart_of_account_id', 'cpg.receivables_account_id')
                    ->whereColumn('gl.document_number', 'cle.document_number');
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
        $inventoryAccountIds = InventoryPostingSetup::query()
            ->whereNotNull('inventory_account_id')
            ->pluck('inventory_account_id')
            ->unique()
            ->values();

        if ($inventoryAccountIds->isEmpty()) {
            return [];
        }

        return DB::table('value_entries as ve')
            ->whereNotNull('ve.document_no')
            ->whereNotExists(function ($query) use ($inventoryAccountIds): void {
                $query->selectRaw('1')
                    ->from('gl_entries as gl')
                    ->whereIn('gl.chart_of_account_id', $inventoryAccountIds->all())
                    ->whereColumn('gl.document_number', 've.document_no');
            })
            ->groupBy('ve.document_type', 've.document_no')
            ->orderBy('ve.document_no')
            ->limit(250)
            ->get([
                've.document_type',
                've.document_no as document_number',
                DB::raw($this->inventoryValueEffectSql('ve', 'amount')),
            ])
            ->map(fn ($entry): array => [
                'control_type' => 'INVENTORY',
                'account_number' => 'INVENTORY_CONTROL_TOTAL',
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'amount' => round((float) $entry->amount, 2),
                'source_hint' => 'Value Entry',
                ...$this->findingMetadata(
                    classification: 'missing_control_account_entry',
                    severity: 'critical',
                    suggestedRemediation: 'This Value Entry document has no matching inventory G/L control entry. Review item/value posting for the document before planning a controlled correction.'
                ),
            ])
            ->values()
            ->all();
    }

    private function glDebitMinusCredit(int $chartOfAccountId): float
    {
        return (float) GlEntry::query()
            ->where('chart_of_account_id', $chartOfAccountId)
            ->sum(DB::raw('debit_amount - credit_amount'));
    }

    private function inventoryValueEffectSql(string $table = 'value_entries', string $alias = 'inventory_value_effect'): string
    {
        return "COALESCE(SUM(CASE WHEN {$table}.item_ledger_entry_type IN (2, 4, 6, 9) THEN -ABS({$table}.cost_amount_actual) ELSE {$table}.cost_amount_actual END), 0) as {$alias}";
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
