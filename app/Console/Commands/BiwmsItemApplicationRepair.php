<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ItemLedgerEntry;
use App\Services\Inventory\ItemApplicationRepairService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('biwms:item-application-repair {--entry= : Item Ledger Entry entry_number or database ID} {--dry-run : Report proposed item application repair without mutating data} {--apply : Apply the controlled repair}')]
#[Description('Report or apply a controlled item application repair for a single outbound Item Ledger Entry.')]
class BiwmsItemApplicationRepair extends Command
{
    public function __construct(
        private readonly ItemApplicationRepairService $repairService,
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

        $this->info('BIWMS Item Application Repair');
        $this->line($apply ? 'Mode: apply. Controlled repair was requested.' : 'Mode: dry-run. No data was changed.');
        $this->line('Dry Run Mutates Data: no');
        $this->line('Entry Number: '.$result['entry_number']);
        $this->line('Item: '.$result['item_code'].' ('.$result['item_id'].')');
        $this->line('Document: '.$result['document_type'].' '.$result['document_number']);
        $this->line('Posting Date: '.$result['posting_date']);
        $this->line('Costing Method: '.$result['costing_method']);
        $this->line('Outbound Quantity: '.number_format((float) $result['outbound_quantity'], 8, '.', ''));
        $this->line('Currently Applied Quantity: '.number_format((float) $result['currently_applied_quantity'], 8, '.', ''));
        $this->line('Missing Quantity: '.number_format((float) $result['missing_quantity'], 8, '.', ''));
        $this->line('Current Outbound Value Entry Economic Cost: '.number_format((float) $result['current_outbound_value_entry_economic_cost'], 4, '.', ''));
        $this->line('Expected Cost After Application: '.number_format((float) $result['expected_cost_after_application'], 4, '.', ''));
        $this->line('Value Entry Adjustment Required: '.($result['value_entry_adjustment_required'] ? 'yes' : 'no'));
        $this->line('G/L Adjustment Required: '.($result['gl_adjustment_required'] ? 'yes' : 'no'));
        $this->line('Eligibility: '.$result['eligibility_classification']);

        if ($result['refusal_reason']) {
            $this->warn('Refusal Reason: '.$result['refusal_reason']);
        }

        $this->line('Candidate Inbound Layers:');
        foreach ($result['candidate_inbound_layers'] as $layer) {
            $this->line(sprintf(
                ' - entry=%s document=%s %s posting_date=%s available=%0.8f unit_cost=%0.8f',
                $layer['entry_number'],
                $layer['document_type'],
                $layer['document_number'],
                $layer['posting_date'],
                $layer['available_quantity'],
                $layer['unit_cost'],
            ));
        }

        $this->line('Proposed Applications:');
        foreach ($result['proposed_applications'] as $application) {
            $this->line(sprintf(
                ' - inbound_entry=%s quantity=%0.8f unit_cost=%0.8f cost=%0.4f',
                $application['entry_number'],
                $application['proposed_applied_quantity'],
                $application['unit_cost'],
                $application['cost_amount'],
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
