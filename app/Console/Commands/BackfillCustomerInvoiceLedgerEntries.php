<?php

namespace App\Console\Commands;

use App\Models\CustomerLedgerEntry;
use App\Models\PostedSalesInvoice;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('sales-invoices:backfill-customer-ledger
    {--apply : Persist changes. Without this flag, the command runs as dry-run}
    {--invoice=* : Optional posted sales invoice document numbers to process}
    {--customer=* : Optional customer IDs to process}')]
#[Description('Backfill missing Customer Ledger debit entries for posted sales invoices')]
class BackfillCustomerInvoiceLedgerEntries extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $invoiceNumbers = array_filter((array) $this->option('invoice'));
        $customerIds = array_map('intval', array_filter((array) $this->option('customer')));

        $fallbackUserId = User::query()->value('id');
        if (! $fallbackUserId) {
            $this->error('No users found. Cannot set created_by for backfilled customer ledger entries.');

            return self::FAILURE;
        }

        $query = PostedSalesInvoice::query()
            ->whereNotNull('posted_at')
            ->when($invoiceNumbers !== [], function (Builder $builder) use ($invoiceNumbers): void {
                $builder->whereIn('document_number', $invoiceNumbers);
            })
            ->when($customerIds !== [], function (Builder $builder) use ($customerIds): void {
                $builder->whereIn('customer_id', $customerIds);
            })
            ->orderBy('posting_date')
            ->orderBy('id');

        $invoices = $query->get();
        if ($invoices->isEmpty()) {
            $this->warn('No posted sales invoices matched the criteria.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'Apply' : 'Dry-run')." mode for {$invoices->count()} posted sales invoice(s).");

        $processed = 0;
        $planned = 0;
        $created = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            $processed++;

            if (! $invoice->customer_id) {
                $skipped++;
                $this->line("SKIP {$invoice->document_number}: missing customer_id.");

                continue;
            }

            $exists = CustomerLedgerEntry::query()
                ->where('document_type', 'SALES_INVOICE')
                ->where('document_number', $invoice->document_number)
                ->where('customer_id', $invoice->customer_id)
                ->exists();

            if ($exists) {
                $skipped++;
                $this->line("SKIP {$invoice->document_number}: ledger entry already exists.");

                continue;
            }

            $planned++;
            $this->line(($apply ? 'CREATE' : 'PLAN')." {$invoice->document_number}");

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use ($invoice, $fallbackUserId): void {
                $lastEntry = CustomerLedgerEntry::query()
                    ->where('customer_id', $invoice->customer_id)
                    ->orderByDesc('entry_number')
                    ->first();

                $nextEntryNumber = ((int) ($lastEntry?->entry_number ?? 0)) + 1;
                $amount = (float) ($invoice->grand_total ?? 0);
                $newRunningBalance = (float) ($lastEntry?->running_balance ?? 0) + $amount;
                $currencyFactor = (float) ($invoice->currency_factor ?? 1);
                if ($currencyFactor <= 0) {
                    $currencyFactor = 1.0;
                }
                $remainingAmount = (float) ($invoice->remaining_amount ?? $amount);

                CustomerLedgerEntry::query()->create([
                    'entry_number' => $nextEntryNumber,
                    'customer_id' => $invoice->customer_id,
                    'document_type' => 'SALES_INVOICE',
                    'document_number' => $invoice->document_number,
                    'external_document_number' => $invoice->external_document_number,
                    'description' => "Invoice {$invoice->document_number}",
                    'posting_date' => ($invoice->posting_date ?? now())->toDateString(),
                    'document_date' => ($invoice->document_date ?? $invoice->posting_date ?? now())->toDateString(),
                    'due_date' => $invoice->due_date?->toDateString(),
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'amount' => $amount,
                    'running_balance' => $newRunningBalance,
                    'remaining_amount' => max(0, $remainingAmount),
                    'open' => ((float) $remainingAmount) > 0.01,
                    'fully_applied' => ((float) $remainingAmount) <= 0.01,
                    'currency_code' => $invoice->currency_code ?: 'NGN',
                    'original_debit_amount' => $currencyFactor === 0.0 ? $amount : ($amount / $currencyFactor),
                    'original_credit_amount' => 0,
                    'currency_factor' => $currencyFactor,
                    'general_business_posting_group_id' => $invoice->general_business_posting_group_id,
                    'customer_posting_group_id' => $invoice->customer_posting_group_id,
                    'gl_entry_id' => $invoice->glEntries()->first()?->id,
                    'source_id' => $invoice->id,
                    'source_type' => PostedSalesInvoice::class,
                    'created_by' => $invoice->posted_by ?: $fallbackUserId,
                ]);
            });

            $created++;
        }

        $this->newLine();
        $this->info("Processed: {$processed}, Planned: {$planned}, Created: {$created}, Skipped: {$skipped}");
        if (! $apply) {
            $this->comment('Dry-run complete. Re-run with --apply to persist changes.');
        }

        return self::SUCCESS;
    }
}
