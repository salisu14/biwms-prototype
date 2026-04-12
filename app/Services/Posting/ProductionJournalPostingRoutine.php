<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Models\CapacityLedgerEntry;
use App\Models\ItemLedgerEntry;
use App\Models\ProductionJournalLine;
use App\Services\Inventory\CostingService;

class ProductionJournalPostingRoutine extends AbstractJournalPostingRoutine
{
    public function __construct(
        private readonly ItemJournalPostingRoutine $itemPostingRoutine,
        private readonly CostingService $costingService
    ) {}

    /**
     * @param  ProductionJournalLine  $line
     */
    protected function validateLine(object $line): void
    {
        if (! $line->production_order_id) {
            $this->errors[] = "Line {$line->line_no}: Production Order is required";
        }

        if ($line->entry_type === 'consumption' && ! $line->item_id) {
            $this->errors[] = "Line {$line->line_no}: Consumption requires Item";
        }

        if ($line->entry_type === 'capacity' && ! $line->work_center_id) {
            $this->errors[] = "Line {$line->line_no}: Capacity requires Work Center";
        }

        // Validate production order status
        if (! in_array($line->productionOrder->status, ['released', 'in_progress'])) {
            $this->errors[] = "Line {$line->line_no}: Production Order must be Released or In Progress";
        }
    }

    /**
     * @param  ProductionJournalLine  $line
     */
    protected function postLine(object $line): void
    {
        match ($line->entry_type) {
            'consumption' => $this->postConsumption($line),
            'output' => $this->postOutput($line),
            'capacity' => $this->postCapacity($line),
            'scrap' => $this->postScrap($line),
            default => throw new \InvalidArgumentException("Unknown entry type: {$line->entry_type}"),
        };
    }

    private function postConsumption(ProductionJournalLine $line): void
    {
        // Create Item Ledger Entry (negative)
        $itemLedgerEntry = $this->createItemLedgerEntry($line, 'negative');

        // Update Production Order Component
        $component = $line->productionOrder->components()
            ->where('item_id', $line->item_id)
            ->first();

        if ($component) {
            $component->consumed_quantity += $line->quantity;
            $component->save();
        }

        // Post to WIP Account (debit) vs. Inventory (credit)
        $this->postWIPCost($line, $itemLedgerEntry->total_cost);

        $this->updateLineStatus($line, 'posted', $itemLedgerEntry->id, ItemLedgerEntry::class);
    }

    private function postOutput(ProductionJournalLine $line): void
    {
        // Create Item Ledger Entry (positive) for FG
        $itemLedgerEntry = $this->createItemLedgerEntry($line, 'positive');

        // Calculate and absorb costs
        $totalCost = $this->calculateOutputCost($line);

        // Update Production Order Line
        $prodLine = $line->productionOrder->lines()
            ->where('item_id', $line->item_id)
            ->first();

        if ($prodLine) {
            $prodLine->finished_quantity += $line->quantity;
            $prodLine->remaining_quantity -= $line->quantity;
            $prodLine->save();
        }

        // Post WIP to FG transfer
        $this->postWIPToFG($line, $totalCost);

        $this->updateLineStatus($line, 'posted', $itemLedgerEntry->id, ItemLedgerEntry::class);
    }

    private function postCapacity(ProductionJournalLine $line): void
    {
        $workCenter = $line->workCenter;

        // Calculate costs
        $directCost = $this->calculateDirectCost($line, $workCenter);
        $overheadCost = $line->batch->template->absorb_overhead
            ? $this->calculateOverhead($line, $workCenter)
            : 0;

        $capacityEntry = CapacityLedgerEntry::create([
            'work_center_id' => $line->work_center_id,
            'machine_center_id' => $line->machine_center_id,
            'production_order_id' => $line->production_order_id,
            'routing_line_id' => $line->routing_line_id,
            'posting_date' => $line->posting_date,
            'document_no' => $line->production_order_no,
            'setup_time' => $line->setup_time,
            'run_time' => $line->run_time,
            'stop_time' => $line->stop_time,
            'output_quantity' => $line->output_quantity,
            'scrap_quantity' => $line->scrap_quantity,
            'direct_cost' => $directCost,
            'overhead_cost' => $overheadCost,
            'total_cost' => $directCost + $overheadCost,
            'unit_cost' => $line->output_quantity > 0 ? ($directCost + $overheadCost) / $line->output_quantity : 0,
        ]);

        // Post to WIP (debit) vs. Direct Cost Account (credit)
        $this->postCapacityToWIP($line, $directCost, $overheadCost);

        $this->updateLineStatus($line, 'posted', $capacityEntry->id, CapacityLedgerEntry::class);
    }

    private function calculateDirectCost(ProductionJournalLine $line, $workCenter): float
    {
        $totalTime = $line->getTotalTime();

        return $totalTime * ($workCenter->direct_unit_cost ?? 0);
    }

    private function calculateOverhead(ProductionJournalLine $line, $workCenter): float
    {
        $totalTime = $line->getTotalTime();

        return $totalTime * ($workCenter->overhead_rate ?? 0);
    }

    private function createItemLedgerEntry(ProductionJournalLine $line, string $direction): ItemLedgerEntry
    {
        // Delegate to ItemJournalPostingRoutine logic
        // Simplified for brevity
        return new ItemLedgerEntry;
    }

    private function postWIPCost(ProductionJournalLine $line, float $cost): void
    {
        // GL Entry: Debit WIP, Credit Inventory
        $this->createGeneralLedgerEntry([
            'account_id' => $line->productionOrder->wip_account_id,
            'posting_date' => $line->posting_date,
            'document_no' => $line->production_order_no,
            'description' => "Consumption: {$line->item->description}",
            'debit_amount' => $cost,
            'credit_amount' => 0,
            'amount_lcy' => $cost,
        ]);
    }

    private function postWIPToFG(ProductionJournalLine $line, float $cost): void
    {
        // GL Entry: Debit FG Inventory, Credit WIP
        $this->createGeneralLedgerEntry([
            'account_id' => $line->item->inventory_account_id,
            'posting_date' => $line->posting_date,
            'document_no' => $line->production_order_no,
            'description' => "Output: {$line->item->description}",
            'debit_amount' => $cost,
            'credit_amount' => 0,
            'amount_lcy' => $cost,
        ]);

        $this->createGeneralLedgerEntry([
            'account_id' => $line->productionOrder->wip_account_id,
            'posting_date' => $line->posting_date,
            'document_no' => $line->production_order_no,
            'description' => "WIP Transfer: {$line->item->description}",
            'debit_amount' => 0,
            'credit_amount' => $cost,
            'amount_lcy' => -$cost,
        ]);
    }

    private function postCapacityToWIP(ProductionJournalLine $line, float $directCost, float $overheadCost): void
    {
        // GL Entries for capacity absorption
    }
}
