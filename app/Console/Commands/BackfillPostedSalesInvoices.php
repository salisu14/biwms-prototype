<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('sales-invoices:backfill-posted
    {--apply : Persist changes. Without this flag, the command runs as dry-run}
    {--invoice=* : Optional invoice numbers to process}')]
#[Description('Backfill posted_sales_invoices and posted_sales_invoice_lines from posted sales_invoices')]
class BackfillPostedSalesInvoices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $invoiceNumbers = array_filter((array) $this->option('invoice'));

        $fallbackPosterId = User::query()->value('id');
        if (! $fallbackPosterId) {
            $this->error('No users found. Cannot set posted_by for backfilled posted invoices.');

            return self::FAILURE;
        }

        $query = SalesInvoice::query()
            ->with(['customer', 'salesOrder', 'lines.item'])
            ->where(function (Builder $builder): void {
                $builder
                    ->where('status', ApprovalStatus::POSTED->value)
                    ->orWhereNotNull('posted_at');
            })
            ->when($invoiceNumbers !== [], function (Builder $builder) use ($invoiceNumbers): void {
                $builder->whereIn('invoice_number', $invoiceNumbers);
            })
            ->orderBy('invoice_number');

        $invoices = $query->get();
        if ($invoices->isEmpty()) {
            $this->warn('No posted sales invoices found to backfill.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'Apply' : 'Dry-run')." mode for {$invoices->count()} sales invoice(s).");

        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->lines->isEmpty()) {
                $skipped++;
                $this->line("SKIP {$invoice->invoice_number}: no lines.");

                continue;
            }

            $exists = PostedSalesInvoice::query()
                ->where('document_number', $invoice->invoice_number)
                ->exists();

            $label = $exists ? 'UPDATE' : 'CREATE';
            $this->line("{$label} {$invoice->invoice_number}");
            $processed++;

            if (! $apply) {
                if ($exists) {
                    $updated++;
                } else {
                    $created++;
                }

                continue;
            }

            DB::transaction(function () use ($invoice, $fallbackPosterId, &$created, &$updated): void {
                $postedInvoice = PostedSalesInvoice::query()->firstOrCreate(
                    ['document_number' => $invoice->invoice_number],
                    [
                        'external_document_number' => null,
                        'order_id' => $invoice->sales_order_id,
                        'order_number' => $invoice->salesOrder?->order_number,
                        'customer_id' => $invoice->customer_id,
                        'customer_name' => $invoice->customer?->name ?? 'Unknown Customer',
                        'customer_address' => $invoice->customer?->address ?? null,
                        'ship_to_name' => $invoice->customer?->name ?? null,
                        'ship_to_address' => $invoice->customer?->address ?? null,
                        'general_business_posting_group_id' => $invoice->customer?->general_business_posting_group_id,
                        'customer_posting_group_id' => $invoice->customer?->customer_posting_group_id,
                        'vat_bus_posting_group' => $invoice->customer?->vat_bus_posting_group,
                        'location_id' => null,
                        'shipping_agent_code' => null,
                        'posting_date' => ($invoice->invoice_date ?? now())->toDateString(),
                        'document_date' => ($invoice->invoice_date ?? now())->toDateString(),
                        'due_date' => ($invoice->due_date ?? now())->toDateString(),
                        'shipment_date' => null,
                        'subtotal' => 0,
                        'line_discount_total' => 0,
                        'invoice_discount_amount' => 0,
                        'total_amount' => 0,
                        'total_vat' => 0,
                        'grand_total' => 0,
                        'currency_code' => $invoice->currency_code ?? 'NGN',
                        'currency_factor' => 1,
                        'amount_paid' => 0,
                        'remaining_amount' => 0,
                        'paid_in_full' => false,
                        'posted_by' => $invoice->posted_by ?: $fallbackPosterId,
                        'posted_at' => $invoice->posted_at ?? now(),
                        'salesperson_id' => null,
                        'cancelled' => false,
                        'dimensions' => null,
                    ]
                );

                if ($postedInvoice->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

                $postedInvoice->lines()->delete();

                $lineNumber = 0;
                $subtotal = 0.0;
                $lineDiscountTotal = 0.0;
                $totalAmount = 0.0;
                $totalVat = 0.0;

                foreach ($invoice->lines as $line) {
                    $lineNumber += 10;

                    $item = $line->item;
                    $quantity = (float) $line->quantity;
                    $unitPrice = (float) $line->unit_price;
                    $lineSubTotal = $quantity * $unitPrice;
                    $discountAmount = (float) ($line->discount_amount ?? 0);
                    $lineAmount = max(0, $lineSubTotal - $discountAmount);
                    $vatAmount = (float) ($line->vat_amount ?? 0);
                    $amountIncludingVat = $lineAmount + $vatAmount;
                    $unitCost = (float) ($item?->unit_cost ?? 0);
                    $costAmount = $quantity * $unitCost;

                    PostedSalesInvoiceLine::query()->create([
                        'posted_sales_invoice_id' => $postedInvoice->id,
                        'so_line_id' => null,
                        'so_line_number' => null,
                        'item_id' => $line->item_id,
                        'item_code' => $item?->item_code,
                        'item_description' => $line->description ?? ($item?->description ?? 'N/A'),
                        'variant_code' => null,
                        'posting_date' => $postedInvoice->posting_date,
                        'general_product_posting_group_id' => $item?->general_product_posting_group_id,
                        'inventory_posting_group_id' => $item?->inventory_posting_group_id,
                        'sales_account_id' => null,
                        'cogs_account_id' => null,
                        'inventory_account_id' => null,
                        'quantity' => $quantity,
                        'unit_of_measure_code' => $line->unit_of_measure ?: ($item?->base_unit_of_measure ?? 'PCS'),
                        'qty_per_unit_of_measure' => 1,
                        'quantity_base' => $quantity,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $unitCost,
                        'unit_cost_lcy' => $unitCost,
                        'line_discount_percent' => (float) ($line->discount_percent ?? 0),
                        'line_discount_amount' => $discountAmount,
                        'line_total' => $lineSubTotal,
                        'line_amount' => $lineAmount,
                        'vat_code' => $item?->vat_product_posting_group_id ? (string) $item->vat_product_posting_group_id : null,
                        'vat_percentage' => (float) ($line->vat_percent ?? 0),
                        'vat_amount' => $vatAmount,
                        'amount_including_vat' => $amountIncludingVat,
                        'cost_amount' => $costAmount,
                        'profit_amount' => $lineAmount - $costAmount,
                        'lot_number' => null,
                        'serial_number' => null,
                        'expiration_date' => null,
                        'item_ledger_entry_id' => null,
                        'shipment_id' => null,
                        'dimensions' => null,
                        'line_number' => $lineNumber,
                    ]);

                    $subtotal += $lineSubTotal;
                    $lineDiscountTotal += $discountAmount;
                    $totalAmount += $lineAmount;
                    $totalVat += $vatAmount;
                }

                $grandTotal = $totalAmount + $totalVat;
                $postedInvoice->update([
                    'subtotal' => $subtotal,
                    'line_discount_total' => $lineDiscountTotal,
                    'invoice_discount_amount' => 0,
                    'total_amount' => $totalAmount,
                    'total_vat' => $totalVat,
                    'grand_total' => $grandTotal,
                    'remaining_amount' => $grandTotal,
                ]);
            });
        }

        $this->newLine();
        $this->info("Processed: {$processed}, Created: {$created}, Updated: {$updated}, Skipped: {$skipped}");
        if (! $apply) {
            $this->comment('Dry-run complete. Re-run with --apply to persist changes.');
        }

        return self::SUCCESS;
    }
}
