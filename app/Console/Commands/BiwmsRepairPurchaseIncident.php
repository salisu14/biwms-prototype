<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Purchase\PurchaseInvoiceIncidentRepairService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('biwms:repair-purchase-incident {--invoice=PI-2026-00001 : Purchase invoice number} {--order=PO-2026-00002 : Purchase order number} {--business=1 : Business ID} {--dry-run : Report the repair plan without mutating data} {--execute : Execute the controlled repair}')]
#[Description('Inspect or execute the controlled repair for the PI-2026-00001 purchase invoice incident.')]
class BiwmsRepairPurchaseIncident extends Command
{
    public function __construct(private readonly PurchaseInvoiceIncidentRepairService $repairService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('execute')) {
            $this->error('Choose either --dry-run or --execute, not both.');

            return self::FAILURE;
        }

        $invoiceNumber = (string) $this->option('invoice');
        $orderNumber = (string) $this->option('order');
        $businessId = $this->option('business');

        if ($invoiceNumber !== 'PI-2026-00001' || $orderNumber !== 'PO-2026-00002' || (int) $businessId !== 1) {
            $this->error('This command is intentionally scoped only to PI-2026-00001 / PO-2026-00002 / business_id=1.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');

        try {
            $result = $execute
                ? $this->repairService->execute($invoiceNumber, $orderNumber, (int) $businessId, auth()->id())
                : $this->repairService->analyze($invoiceNumber, $orderNumber, (int) $businessId);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('BIWMS Purchase Invoice Incident Repair');
        $this->line($execute ? 'Mode: execute. Controlled repair was requested.' : 'Mode: dry-run. No data was changed.');
        $this->line('Invoice: '.$result['invoice_number']);
        $this->line('Purchase Order: '.$result['purchase_order_number']);
        $this->line('Business: '.$result['business_id']);
        $this->line('Amount: '.$result['amount'].' '.$result['currency_code']);
        $this->line('Payables account ID: '.$result['payables_account_id']);
        $this->line('GRNI account ID: '.$result['gl']['grni_account_id']);
        $this->line('Replacement transaction key: '.$result['replacement_transaction_key']);

        if ($result['already_repaired']) {
            $this->warn('Already repaired; no action is required.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('G/L historical groups detected:');
        foreach ($result['gl']['malformed_summary'] as $entry) {
            $this->line(sprintf(
                ' - Tx %s GlEntry #%s account=%s debit=%s credit=%s',
                $entry['transaction_number'],
                $entry['id'],
                $entry['account_id'],
                $entry['debit'],
                $entry['credit'],
            ));
        }

        $this->line('G/L repair plan: append neutralizer rows for the malformed groups, then post one balanced replacement Dr GRNI / Cr Payables transaction through the posting kernel.');
        $this->line('Vendor ledger plan: convert the known legacy debit-side invoice representation to the canonical credit-side payable representation.');
        $this->line('ILE plan: synchronize receipt ILE actual costs from the existing actual Value Entries.');

        foreach ($result['item_ledger_entries'] as $entry) {
            $this->line(sprintf(
                ' - ILE #%s current_actual=%s authoritative_actual=%s',
                $entry['entry_number'],
                $entry['current_cost_amount_actual'],
                $entry['authoritative_value_entry_cost'],
            ));
        }

        $this->line('Records not touched: '.implode(', ', $result['records_not_touched']));

        if ($execute && ! ($result['executed'] ?? false) && ! ($result['idempotent'] ?? false)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
