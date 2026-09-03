<?php

namespace App\Console\Commands;

use App\Models\CustomerLedgerApplication;
use App\Models\CustomerLedgerEntry;
use App\Models\GlEntry;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\SubledgerOpeningBalance;
use App\Models\VendorLedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BiwmsSubledgerReconcile extends Command
{
    protected $signature = 'biwms:subledger-reconcile {--details : Show every finding}';

    protected $description = 'Report-only customer and vendor settlement integrity findings';

    /** @var array<int, array{code:string,severity:string,message:string,remediation:string}> */
    private array $findings = [];

    public function handle(): int
    {
        $this->findings = [];

        if (Schema::hasTable('payment_applications')) {
            $this->inspectPaymentApplications();
        }

        if (Schema::hasTable('customer_ledger_applications')) {
            $this->inspectCustomerApplications();
        }

        if (Schema::hasTable('customer_ledger_entries')) {
            $this->inspectLedgerOwnership(CustomerLedgerEntry::query()->cursor(), 'customer');
        }

        if (Schema::hasTable('vendor_ledger_entries')) {
            $this->inspectLedgerOwnership(VendorLedgerEntry::query()->cursor(), 'vendor');
        }

        if (Schema::hasTable('subledger_opening_balances')) {
            $this->inspectOpeningBalances();
        }

        $this->inspectStatusConsistency();

        $this->components->info('BIWMS subledger reconciliation (report-only)');
        $this->components->twoColumnDetail('Findings', (string) count($this->findings));

        foreach ($this->findings as $finding) {
            if ($this->option('details')) {
                $this->line(sprintf(
                    '[%s] %s: %s',
                    strtoupper($finding['severity']),
                    $finding['code'],
                    $finding['message'],
                ));
                $this->line('  Remediation: '.$finding['remediation']);
            }
        }

        if ($this->findings === []) {
            $this->components->success('No customer/vendor subledger findings detected.');
        }

        return self::SUCCESS;
    }

    private function inspectPaymentApplications(): void
    {
        $seen = [];
        $checkedPayments = [];
        $checkedDocuments = [];

        PaymentApplication::query()->with('payment')->cursor()->each(function (PaymentApplication $application) use (&$seen): void {
            $payment = $application->payment;

            if (! $payment) {
                $this->finding('orphan_application', 'critical', "Payment application {$application->id} has no payment.", 'Review the application and payment linkage before any controlled correction.');

                return;
            }

            if ($application->business_id !== null && $payment->business_id !== null
                && (int) $application->business_id !== (int) $payment->business_id) {
                $this->finding('cross_business_payment_application', 'critical', "Payment application {$application->id} differs from payment {$payment->id} business.", 'Review ownership from the source payment and target document; do not reassign automatically.');
            }

            $activeAmount = (float) PaymentApplication::query()
                ->where('payment_id', $payment->id)
                ->where('reversed', false)
                ->sum('amount_applied');

            if (! isset($checkedPayments[$payment->id])) {
                if (abs($activeAmount - (float) $payment->applied_amount) > 0.0001) {
                    $this->finding('application_total_mismatch', 'critical', "Payment {$payment->payment_number} applied amount disagrees with active canonical applications.", 'Reconcile active payment applications and payment totals through the settlement service.');
                }

                $checkedPayments[$payment->id] = true;
            }

            if ($activeAmount - (float) $payment->payment_amount > 0.0001) {
                $this->finding('application_total_exceeds_payment', 'critical', "Payment {$payment->payment_number} has applications exceeding its amount.", 'Reconcile canonical application rows against the payment before posting any correction.');
            }

            $invariant = (float) $payment->applied_amount + (float) $payment->unapplied_amount;
            if (abs($invariant - (float) $payment->payment_amount) > 0.0001) {
                $this->finding('payment_applied_unapplied_mismatch', 'critical', "Payment {$payment->payment_number} applied plus unapplied does not equal payment amount.", 'Reconcile active canonical applications and payment totals through the settlement service.');
            }

            $duplicateKey = implode('|', [
                $application->payment_id,
                $application->document_type,
                $application->document_id,
                (string) $application->amount_applied,
                (string) $application->document_remaining_before,
                (string) $application->document_remaining_after,
            ]);
            if (! $application->reversed && isset($seen[$duplicateKey])) {
                $this->finding('duplicate_canonical_application', 'critical', "Payment applications {$seen[$duplicateKey]} and {$application->id} represent the same active settlement snapshot.", 'Review the canonical application rows and preserve any legitimate reversal history; do not delete rows automatically.');
            }
            $seen[$duplicateKey] = $application->id;

            if ($application->document_type === 'SALES_INVOICE') {
                $document = PostedSalesInvoice::query()->find($application->document_id);
            } elseif ($application->document_type === 'SALES_CREDIT_MEMO') {
                $document = PostedSalesCreditMemo::query()->find($application->document_id);
            } elseif ($application->document_type === 'PURCHASE_INVOICE') {
                $document = PostedPurchaseInvoice::query()->find($application->document_id);
            } elseif ($application->document_type === 'PURCHASE_CREDIT_MEMO') {
                $document = PostedPurchaseCreditMemo::query()->find($application->document_id);
            } else {
                $document = null;
            }

            if (! $document) {
                $this->finding('orphan_application_document', 'critical', "Payment application {$application->id} has no matching posted document.", 'Trace the application to its posted invoice or credit memo before any controlled correction.');
            }

            if ($document && $application->business_id !== null && ($document->business_id ?? null) !== null
                && (int) $application->business_id !== (int) $document->business_id) {
                $this->finding('cross_business_document_application', 'critical', "Payment application {$application->id} differs from document {$application->document_id} business.", 'Review the owning business on both source and target documents; do not apply across businesses.');
            }

            if ($document && $application->amount_applied - (float) $application->document_original_amount > 0.0001) {
                $this->finding('application_exceeds_document', 'critical', "Payment application {$application->id} exceeds the document snapshot amount.", 'Compare the application with the posted document and its ledger entry before correction.');
            }

            if ($document) {
                $expectedPartyType = in_array($application->document_type, ['SALES_INVOICE', 'SALES_CREDIT_MEMO'], true)
                    ? 'CUSTOMER'
                    : 'VENDOR';
                $documentPartyId = $document->customer_id ?? $document->vendor_id;

                if ($payment->party_type !== $expectedPartyType || (int) $payment->party_id !== (int) $documentPartyId) {
                    $this->finding('application_party_mismatch', 'critical', "Payment application {$application->id} targets a different party than its payment/document.", 'Review payment, document, and party ownership; do not apply across customers or vendors.');
                }

                $documentKey = $application->document_type.'|'.$application->document_id;
                if (! isset($checkedDocuments[$documentKey])) {
                    $this->inspectDocumentLedgerConsistency($application, $document, (int) $documentPartyId);
                    $checkedDocuments[$documentKey] = true;
                }
            }

            if ($document && strtoupper((string) $payment->currency_code) !== strtoupper((string) $document->currency_code)) {
                $this->finding('application_currency_mismatch', 'critical', "Payment application {$application->id} crosses payment/document currencies.", 'Use an explicit supported currency conversion or reverse the invalid application through the approved service.');
            }

            if ($document) {
                $expectedRemaining = (float) $document->grand_total - (float) $document->amount_paid;
                if (abs($expectedRemaining - (float) $document->remaining_amount) > 0.0001) {
                    $this->finding('document_remaining_mismatch', 'critical', "Document {$document->document_number} remaining amount disagrees with amount paid.", 'Reconcile document and ledger state through the settlement service.');
                }
            }

            if ((float) $application->gain_loss_amount !== 0.0 && Schema::hasColumn('gl_entries', 'payment_application_id')) {
                $fxEntries = GlEntry::query()->where('payment_application_id', $application->id)->get();
                if ($fxEntries->isEmpty()) {
                    $this->finding('fx_application_missing_gl', 'critical', "Application {$application->id} has realized FX but no linked G/L entries.", 'Trace the application posting transaction and create a controlled reversal/correction plan.');
                }

                if ($application->reversed) {
                    foreach ($fxEntries as $fxEntry) {
                        $reversalCount = GlEntry::query()->where('reversal_of_gl_entry_id', $fxEntry->id)->count();
                        if ($reversalCount === 0) {
                            $this->finding('fx_unapplication_missing_reversal', 'critical', "Reversed application {$application->id} has no reversal for FX G/L entry {$fxEntry->id}.", 'Create a controlled FX reversal through the approved settlement reversal workflow.');
                        } elseif ($reversalCount > 1) {
                            $this->finding('duplicate_fx_reversal', 'critical', "FX G/L entry {$fxEntry->id} has multiple reversal rows.", 'Review reversal idempotency before any corrective action.');
                        }
                    }
                }
            }
        });

        if (Schema::hasColumn('gl_entries', 'payment_application_id')) {
            GlEntry::query()->whereNotNull('payment_application_id')->cursor()->each(function (GlEntry $entry): void {
                if (! PaymentApplication::query()->whereKey($entry->payment_application_id)->exists()) {
                    $this->finding('orphan_fx_gl_entry', 'critical', "G/L entry {$entry->id} references missing payment application {$entry->payment_application_id}.", 'Trace the source posting transaction before any controlled correction.');
                }
            });

            GlEntry::query()
                ->whereNull('payment_application_id')
                ->where(function ($query): void {
                    $query->where('description', 'like', '%Realized Gain%')
                        ->orWhere('description', 'like', '%Realized Loss%');
                })
                ->cursor()
                ->each(function (GlEntry $entry): void {
                    $this->finding('legacy_fx_gl_without_structured_application', 'warning', "G/L entry {$entry->id} appears to be historical realized FX without structured application linkage.", 'Review legacy FX lineage manually; new FX postings must carry payment_application_id.');
                });
        }
    }

    private function inspectCustomerApplications(): void
    {
        CustomerLedgerApplication::query()->with(['sourceCreditMemo', 'targetInvoice'])->cursor()->each(function (CustomerLedgerApplication $application): void {
            $sourceBusiness = $application->sourceCreditMemo?->business_id;
            $targetBusiness = $application->targetInvoice?->business_id;

            if ($sourceBusiness !== null && $targetBusiness !== null && (int) $sourceBusiness !== (int) $targetBusiness) {
                $this->finding('cross_business_credit_application', 'critical', "Customer ledger application {$application->id} crosses businesses.", 'Review the credit memo and invoice ownership; do not mutate historical application rows.');
            }

            if (! $application->sourceLedgerEntry || ! $application->targetLedgerEntry) {
                $this->finding('orphan_customer_application', 'critical', "Customer ledger application {$application->id} has an orphan ledger link.", 'Trace the original ledger entries and preserve the application history before correction.');
            }

            if ($application->sourceLedgerEntry?->customer_id !== $application->targetLedgerEntry?->customer_id
                || $application->customer_id !== $application->sourceLedgerEntry?->customer_id) {
                $this->finding('customer_application_party_mismatch', 'critical', "Customer ledger application {$application->id} has inconsistent customer parties.", 'Review the immutable source and target lineage before any correction.');
            }

            if ($application->sourceLedgerEntry && $application->targetLedgerEntry
                && filled($application->sourceLedgerEntry->currency_code)
                && filled($application->targetLedgerEntry->currency_code)
                && strtoupper((string) $application->sourceLedgerEntry->currency_code) !== strtoupper((string) $application->targetLedgerEntry->currency_code)) {
                $this->finding('customer_application_currency_mismatch', 'critical', "Customer ledger application {$application->id} crosses currencies.", 'Reject unsupported cross-currency settlement or use an explicit conversion flow.');
            }

            $snapshots = collect($application->sourceLedgerEntry?->applied_to_entries ?? [])
                ->filter(fn (array $snapshot): bool => (int) ($snapshot['entry_id'] ?? 0) === (int) $application->target_customer_ledger_entry_id
                    && abs((float) ($snapshot['amount'] ?? 0) - (float) $application->amount) <= 0.0001);
            if ($snapshots->contains(fn (array $snapshot): bool => ($snapshot['trace_type'] ?? null) !== CustomerLedgerApplication::class)) {
                $this->finding('legacy_canonical_duplicate_application', 'critical', "Customer application {$application->id} also has an unmarked legacy monetary snapshot.", 'Confirm whether the legacy snapshot was historical or duplicated before planning a controlled correction.');
            }
        });
    }

    private function inspectDocumentLedgerConsistency(PaymentApplication $application, object $document, int $partyId): void
    {
        $isCustomer = in_array($application->document_type, ['SALES_INVOICE', 'SALES_CREDIT_MEMO'], true);
        $ledger = $isCustomer
            ? CustomerLedgerEntry::query()
                ->where('customer_id', $partyId)
                ->where('document_type', $application->document_type)
                ->where('document_number', $application->document_number)
                ->first()
            : VendorLedgerEntry::query()
                ->where('vendor_id', $partyId)
                ->where('document_type', $application->document_type)
                ->where('document_number', $application->document_number)
                ->first();

        if (! $ledger) {
            $this->finding('orphan_application_ledger', 'critical', "Payment application {$application->id} has no corresponding {$application->document_type} ledger entry.", 'Trace the posted document and subledger posting before any controlled correction.');

            return;
        }

        $activeApplications = PaymentApplication::query()
            ->where('document_type', $application->document_type)
            ->where('document_id', $application->document_id)
            ->where('reversed', false)
            ->get();
        $appliedTotal = $activeApplications->sum(
            fn (PaymentApplication $row): float => (float) $row->amount_applied
                + (float) $row->discount_applied
                + (float) $row->write_off_amount
        );
        $originalAmount = abs((float) ($ledger->original_debit_amount ?: $ledger->original_credit_amount ?: $ledger->amount));
        $expectedRemaining = max(0, $originalAmount - $appliedTotal);

        if (abs($expectedRemaining - (float) $ledger->remaining_amount) > 0.0001) {
            $this->finding('ledger_remaining_mismatch', 'critical', "Ledger entry {$ledger->id} remaining amount disagrees with canonical applications for {$application->document_number}.", 'Reconcile the document ledger and canonical applications through the settlement service.');
        }

        $expectedOpen = $expectedRemaining > 0.0001;
        if ((bool) $ledger->open !== $expectedOpen) {
            $this->finding('ledger_status_mismatch', 'critical', "Ledger entry {$ledger->id} open status disagrees with its remaining amount.", 'Reconcile the ledger status through the approved settlement or reversal workflow.');
        }

        if (abs($expectedRemaining - (float) ($document->remaining_amount ?? 0)) > 0.0001) {
            $this->finding('document_ledger_remaining_mismatch', 'critical', "Document {$document->document_number} remaining amount disagrees with its ledger entry.", 'Reconcile the posted document and subledger before any correction.');
        }
    }

    /** @param iterable<int, CustomerLedgerEntry|VendorLedgerEntry> $entries */
    private function inspectLedgerOwnership(iterable $entries, string $party): void
    {
        foreach ($entries as $entry) {
            $source = $entry->source;
            $sourceBusiness = $source?->business_id;

            if ($entry->business_id !== null && $sourceBusiness !== null && (int) $entry->business_id !== (int) $sourceBusiness) {
                $this->finding('cross_business_'.$party.'_ledger', 'critical', "{$party} ledger entry {$entry->id} differs from its source business.", 'Review source ownership and ledger lineage; do not assign a business from session context.');
            }
        }
    }

    private function inspectStatusConsistency(): void
    {
        foreach ([
            [PostedSalesInvoice::class, 'sales invoice'],
            [PostedPurchaseInvoice::class, 'purchase invoice'],
        ] as [$model, $label]) {
            $model::query()->where('paid_in_full', true)->where('remaining_amount', '>', 0.0001)->each(function ($document) use ($label): void {
                $this->finding('paid_open_status_contradiction', 'warning', "{$label} {$document->document_number} is marked paid with a remaining amount.", 'Reconcile the document and ledger state through the settlement service.');
            });
        }
    }

    private function inspectOpeningBalances(): void
    {
        $sourceCounts = [];
        $postingCounts = [];

        SubledgerOpeningBalance::query()->cursor()->each(function (SubledgerOpeningBalance $opening): void {
            $ledger = $opening->party_type === 'CUSTOMER'
                ? $opening->customerLedgerEntry
                : $opening->vendorLedgerEntry;

            if ($opening->status === SubledgerOpeningBalance::STATUS_POSTED && ! $ledger) {
                $this->finding('opening_balance_missing_subledger', 'critical', "Posted opening balance {$opening->document_number} has no linked {$opening->party_type} ledger entry.", 'Trace the posting transaction and create a controlled correction plan; do not backfill automatically.');
            }

            if ($opening->status === SubledgerOpeningBalance::STATUS_POSTED && ! $opening->posting_transaction_id) {
                $this->finding('opening_balance_missing_gl_transaction', 'critical', "Posted opening balance {$opening->document_number} has no posting transaction link.", 'Trace the G/L posting and opening document lineage before any correction.');
            }

            if ($opening->status === SubledgerOpeningBalance::STATUS_POSTED && ! $opening->source_id) {
                $this->finding('opening_balance_missing_source', 'critical', "Posted opening balance {$opening->document_number} has no source identity.", 'Preserve the opening document source identity before any controlled correction.');
            }

            $transaction = $opening->postingTransaction;
            $glEntries = $transaction?->glEntries ?? collect();
            if ($opening->status === SubledgerOpeningBalance::STATUS_POSTED && $glEntries->isNotEmpty()) {
                $debit = (float) $glEntries->sum('debit_amount');
                $credit = (float) $glEntries->sum('credit_amount');
                if (abs($debit - $credit) > 0.0001) {
                    $this->finding('opening_balance_unbalanced_gl', 'critical', "Opening balance {$opening->document_number} has an unbalanced G/L transaction.", 'Trace the original posting transaction and prepare a controlled reversal/correction plan.');
                }

                $controlLine = $glEntries->firstWhere('chart_of_account_id', $opening->control_account_id);
                $equityLine = $glEntries->firstWhere('chart_of_account_id', $opening->opening_equity_account_id);
                $expectedControlDebit = $opening->party_type === 'CUSTOMER' ? (float) $opening->amount_lcy : 0.0;
                $expectedControlCredit = $opening->party_type === 'VENDOR' ? (float) $opening->amount_lcy : 0.0;
                if (! $controlLine || abs((float) $controlLine->debit_amount - $expectedControlDebit) > 0.0001
                    || abs((float) $controlLine->credit_amount - $expectedControlCredit) > 0.0001) {
                    $this->finding($opening->party_type === 'CUSTOMER' ? 'opening_balance_wrong_receivables_account' : 'opening_balance_wrong_payables_account', 'critical', "Opening balance {$opening->document_number} does not post the expected party control account amount.", 'Compare the immutable posting-group snapshot with the G/L transaction and plan a controlled correction.');
                }

                $expectedEquityDebit = $opening->party_type === 'VENDOR' ? (float) $opening->amount_lcy : 0.0;
                $expectedEquityCredit = $opening->party_type === 'CUSTOMER' ? (float) $opening->amount_lcy : 0.0;
                if (! $equityLine || abs((float) $equityLine->debit_amount - $expectedEquityDebit) > 0.0001
                    || abs((float) $equityLine->credit_amount - $expectedEquityCredit) > 0.0001) {
                    $this->finding('opening_balance_wrong_equity_account', 'critical', "Opening balance {$opening->document_number} does not post the configured opening equity account correctly.", 'Compare the setup snapshot with the original G/L transaction before any correction.');
                }
            }

            if ($ledger && (int) $ledger->business_id !== (int) $opening->business_id) {
                $this->finding('opening_balance_business_mismatch', 'critical', "Opening balance {$opening->document_number} differs from its subledger business.", 'Review document ownership and ledger lineage; do not reassign historical rows automatically.');
            }

            if ($opening->status === SubledgerOpeningBalance::STATUS_REVERSED) {
                $ledgerModel = $opening->party_type === 'CUSTOMER'
                    ? CustomerLedgerEntry::class
                    : VendorLedgerEntry::class;
                $partyColumn = $opening->party_type === 'CUSTOMER' ? 'customer_id' : 'vendor_id';
                $lifecycleEntries = $ledgerModel::query()
                    ->where('business_id', $opening->business_id)
                    ->where($partyColumn, $opening->{$partyColumn})
                    ->where('source_type', SubledgerOpeningBalance::class)
                    ->where('source_id', $opening->id)
                    ->get();

                if (! $ledger) {
                    $this->finding('opening_balance_reversal_missing_subledger', 'critical', "Reversed opening balance {$opening->document_number} has no original subledger entry.", 'Trace the original and reversal ledger lineage before any controlled correction.');
                }

                if ($ledger && ! $ledger->reversed) {
                    $this->finding('opening_balance_reversal_mismatch', 'critical', "Opening balance {$opening->document_number} is reversed but its original subledger entry is not reversed.", 'Trace the reversal transaction and complete correction through the approved reversal service.');
                }

                if ($lifecycleEntries->count() < 2) {
                    $this->finding('opening_balance_missing_reversal_subledger', 'critical', "Reversed opening balance {$opening->document_number} has no corresponding reversal subledger entry.", 'Trace the reversal transaction and complete correction through the approved reversal service.');
                }

                $effectiveAmount = (float) $lifecycleEntries->sum('amount');
                if (abs($effectiveAmount) > 0.0001) {
                    $this->finding('opening_balance_reversal_exposure', 'critical', "Reversed opening balance {$opening->document_number} retains non-zero effective subledger exposure.", 'Reconcile the original and reversal ledger entries through the approved reversal workflow.');
                }

                $remainingExposure = (float) $lifecycleEntries->sum(fn ($entry): float => abs((float) $entry->remaining_amount));
                if ($remainingExposure > 0.0001) {
                    $this->finding('opening_balance_reversal_open_exposure', 'critical', "Reversed opening balance {$opening->document_number} retains open subledger exposure.", 'Reconcile remaining amounts through the approved settlement or reversal workflow.');
                }
            } elseif ($ledger && abs((float) $ledger->remaining_amount - (float) $opening->remaining_amount_lcy) > 0.0001) {
                $this->finding('opening_balance_remaining_mismatch', 'critical', "Opening balance {$opening->document_number} remaining amount differs from its subledger entry.", 'Reconcile applications and remaining amounts through the approved settlement workflow.');
            }

            if ($opening->status === SubledgerOpeningBalance::STATUS_REVERSED && $opening->posting_transaction_id) {
                $reversalCount = GlEntry::query()
                    ->where('reversal_of_transaction_id', $opening->posting_transaction_id)
                    ->count();
                if ($reversalCount === 0) {
                    $this->finding('opening_balance_missing_reversal_gl', 'critical', "Reversed opening balance {$opening->document_number} has no linked reversal G/L entries.", 'Trace the reversal transaction and complete correction through the approved reversal service.');
                }
            }

            $expectedLcy = round((float) $opening->original_amount * (float) $opening->currency_factor, 4);
            if (abs($expectedLcy - (float) $opening->amount_lcy) > 0.0001) {
                $this->finding('opening_balance_currency_mismatch', 'critical', "Opening balance {$opening->document_number} LCY amount differs from its currency factor.", 'Review the original amount and exchange-rate snapshot before any controlled correction.');
            }
        });

        SubledgerOpeningBalance::query()
            ->select('source_type', 'source_id')
            ->whereNotNull('source_id')
            ->get()
            ->each(function (SubledgerOpeningBalance $opening) use (&$sourceCounts): void {
                $key = $opening->source_type.'|'.$opening->source_id;
                $sourceCounts[$key] = ($sourceCounts[$key] ?? 0) + 1;
            });
        foreach ($sourceCounts as $key => $count) {
            if ($count > 1) {
                $this->finding('opening_balance_duplicate_source', 'critical', "Opening balance source {$key} has {$count} documents.", 'Review duplicate opening source identities before any controlled correction.');
            }
        }

        SubledgerOpeningBalance::query()
            ->whereNotNull('posting_transaction_id')
            ->select('posting_transaction_id')
            ->get()
            ->each(function (SubledgerOpeningBalance $opening) use (&$postingCounts): void {
                $key = (string) $opening->posting_transaction_id;
                $postingCounts[$key] = ($postingCounts[$key] ?? 0) + 1;
            });
        foreach ($postingCounts as $transactionId => $count) {
            if ($count > 1) {
                $this->finding('opening_balance_duplicate_posting', 'critical', "Posting transaction {$transactionId} is linked to {$count} opening documents.", 'Review posting idempotency and source lineage before any controlled correction.');
            }
        }
    }

    private function finding(string $code, string $severity, string $message, string $remediation): void
    {
        $this->findings[] = compact('code', 'severity', 'message', 'remediation');
    }
}
