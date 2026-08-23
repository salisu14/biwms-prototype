<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ItemLedgerEntry;
use App\Services\Inventory\HistoricalSalesShipmentCostRepairService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('biwms:item-application-cost-repair {--entry= : Item Ledger Entry entry_number or database ID} {--dry-run : Report proposed economic repair without mutating data} {--apply : Apply the controlled economic repair}')]
#[Description('Report or apply a controlled historical sales shipment item application and cost correction.')]
class BiwmsItemApplicationCostRepair extends Command
{
    public function __construct(
        private readonly HistoricalSalesShipmentCostRepairService $repairService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        $entry = $this->resolveEntry((string) $this->option('entry'));
        if (! $entry) {
            $this->error('A single Item Ledger Entry must be selected with --entry={entry_number_or_id}.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        try {
            $result = $apply
                ? $this->repairService->repair($entry)
                : $this->repairService->analyze($entry);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('BIWMS Item Application Cost Repair');
        $this->line($apply ? 'Mode: apply. Controlled economic repair was requested.' : 'Mode: dry-run. No data was changed.');
        $this->line('Dry Run Mutates Data: no');
        $this->line('Entry Number: '.$result['entry_number']);
        $this->line('Item: '.$result['item_code'].' ('.$result['item_id'].')');
        $this->line('Document: '.$result['document_type'].' '.$result['document_number']);
        $this->line('Posting Date: '.$result['posting_date']);
        $this->line('Costing Method: '.$result['costing_method']);
        $this->line('Outbound Quantity: '.number_format((float) $result['outbound_quantity'], 8, '.', ''));
        $this->line('Currently Applied Quantity: '.number_format((float) $result['currently_applied_quantity'], 8, '.', ''));
        $this->line('Missing Quantity: '.number_format((float) $result['missing_quantity'], 8, '.', ''));
        $this->line('Old Outbound ILE Cost: '.number_format((float) $result['old_outbound_ile_cost'], 4, '.', ''));
        $this->line('Corrected Outbound ILE Cost: '.number_format((float) $result['corrected_outbound_cost'], 4, '.', ''));
        $this->line('Old Value Entry Economic Cost: '.number_format((float) $result['old_value_entry_economic_cost'], 4, '.', ''));
        $this->line('Corrected Value Entry Economic Cost: '.number_format((float) $result['corrected_value_entry_economic_cost'], 4, '.', ''));
        $this->line('Adjustment Delta: '.number_format((float) $result['adjustment_delta'], 4, '.', ''));
        $this->line('G/L Currency Rounded Delta: '.number_format(abs((float) $result['gl_currency_rounded_delta']), 2, '.', ''));
        $this->line('Expected G/L Debit: '.$result['expected_gl_debit']);
        $this->line('Expected G/L Credit: '.$result['expected_gl_credit']);
        $this->line('Value Entry Adjustment Required: '.($result['value_entry_adjustment_required'] ? 'yes' : 'no'));
        $this->line('G/L Adjustment Required: '.($result['gl_adjustment_required'] ? 'yes' : 'no'));
        $this->line('Eligibility: '.$result['eligibility_classification']);

        if ($result['refusal_reason']) {
            $this->warn('Refusal Reason: '.$result['refusal_reason']);
        }

        $this->line('Historically Reconstructed Candidate Layers:');
        foreach ($result['candidate_inbound_layers'] as $layer) {
            $this->line(sprintf(
                ' - entry=%s document=%s %s posting_date=%s original=%0.8f prior_applied=%0.8f historical_available=%0.8f current_applied=%0.8f current_remaining=%0.8f unit_cost=%0.8f',
                $layer['entry_number'],
                $layer['document_type'],
                $layer['document_number'],
                $layer['posting_date'],
                $layer['original_quantity'],
                $layer['historical_prior_applied_quantity'],
                $layer['historical_available_quantity'],
                $layer['current_non_reversed_applied_quantity'],
                $layer['current_remaining_quantity'],
                $layer['unit_cost'],
            ));
        }

        $this->line('Proposed Applications:');
        foreach ($result['proposed_applications'] as $application) {
            $this->line(sprintf(
                ' - inbound_entry=%s quantity=%0.8f unit_cost=%0.8f cost=%0.4f projected_current_remaining=%0.8f',
                $application['entry_number'],
                $application['proposed_applied_quantity'],
                $application['unit_cost'],
                $application['cost_amount'],
                $application['projected_current_remaining_quantity'],
            ));
        }

        $this->line('Later Application Impacts:');
        foreach ($result['later_application_impacts'] as $impact) {
            $this->line(sprintf(
                ' - inbound_entry=%s later_applied=%0.8f projected_current_remaining=%0.8f',
                $impact['entry_number'],
                $impact['later_non_reversed_applied_quantity'],
                $impact['projected_current_remaining_quantity'],
            ));
        }

        if ($apply && ! ($result['repaired'] ?? false) && ! ($result['idempotent'] ?? false)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveEntry(string $entry): ?ItemLedgerEntry
    {
        if ($entry === '') {
            return null;
        }

        $entryNumberMatches = ItemLedgerEntry::query()
            ->where('entry_number', $entry)
            ->limit(2)
            ->get();

        if ($entryNumberMatches->count() === 1) {
            return $entryNumberMatches->first();
        }

        if ($entryNumberMatches->count() > 1 || ! is_numeric($entry)) {
            return null;
        }

        return ItemLedgerEntry::query()->find((int) $entry);
    }
}
