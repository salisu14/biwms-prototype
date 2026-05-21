<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionJournalEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\Bin;
use App\Models\CapacityLedgerEntry;
use App\Models\InventoryPostingSetup;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
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

        if ($line->entry_type === ProductionJournalEntryType::Consumption && ! $line->item_id) {
            $this->errors[] = "Line {$line->line_no}: Consumption requires Item";
        }

        if ($line->entry_type === ProductionJournalEntryType::Capacity && ! $line->work_center_id) {
            $this->errors[] = "Line {$line->line_no}: Capacity requires Work Center";
        }

        // Validate production order status
        if ($line->productionOrder->status !== ProductionOrderStatus::RELEASED) {
            $this->errors[] = "Line {$line->line_no}: Production Order must be Released";
        }
    }

    /**
     * @param  ProductionJournalLine  $line
     */
    protected function postLine(object $line): void
    {
        match ($line->entry_type) {
            ProductionJournalEntryType::Consumption => $this->postConsumption($line),
            ProductionJournalEntryType::Output => $this->postOutput($line),
            ProductionJournalEntryType::Capacity => $this->postCapacity($line),
            ProductionJournalEntryType::Scrap => $this->postScrap($line),
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
            $component->actual_quantity_consumed += $line->quantity;
            $component->remaining_quantity -= $line->quantity;
            $component->save();
        }

        // Post to WIP Account (debit) vs. Inventory (credit)
        $this->postWIPCost($line, (float) $itemLedgerEntry->cost_amount_actual);

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
            if ($prodLine->remaining_quantity <= 0) {
                $prodLine->finished = true;
                $prodLine->finished_at = now();
                $prodLine->save();
            }
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
            'document_number' => $line->production_order_no,
            'setup_time' => $line->setup_time ?? 0,
            'run_time' => $line->run_time ?? 0,
            'stop_time' => $line->stop_time ?? 0,
            'setup_time_unit' => $line->routingLine?->setup_time_unit ?? 'MINUTES',
            'run_time_unit' => $line->routingLine?->run_time_unit ?? 'MINUTES',
            'output_quantity' => $line->output_quantity ?? 0,
            'scrap_quantity' => $line->scrap_quantity ?? 0,
            'direct_cost' => $directCost,
            'overhead_cost' => $overheadCost,
            'total_cost' => $directCost + $overheadCost,
            'unit_cost' => $line->output_quantity > 0 ? ($directCost + $overheadCost) / $line->output_quantity : 0,
        ]);

        // Post to WIP (debit) vs. Direct Cost Account (credit)
        $this->postCapacityToWIP($line, $directCost, $overheadCost);

        $this->updateLineStatus($line, 'posted', $capacityEntry->id, CapacityLedgerEntry::class);
    }

    private function postScrap(ProductionJournalLine $line): void
    {
        // Create Item Ledger Entry (negative)
        $itemLedgerEntry = $this->createItemLedgerEntry($line, 'negative');

        // Update Production Order Component
        $component = $line->productionOrder->components()
            ->where('item_id', $line->item_id)
            ->first();

        if ($component) {
            $component->actual_quantity_consumed += $line->quantity;
            $component->actual_scrap_quantity += $line->quantity;
            $component->remaining_quantity -= $line->quantity;
            $component->save();
        }

        // Post to WIP Account (debit) vs. Inventory (credit)
        $this->postWIPCost($line, (float) $itemLedgerEntry->cost_amount_actual);

        $this->updateLineStatus($line, 'posted', $itemLedgerEntry->id, ItemLedgerEntry::class);
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

    private function calculateOutputCost(ProductionJournalLine $line): float
    {
        $unitCost = (float) ($line->unit_cost ?? 0);
        if ($unitCost === 0.0 && $line->item) {
            $unitCost = $this->costingService->getUnitCost(
                item: $line->item,
                location: $line->location,
                lotNo: $line->lot_no,
                asOfDate: $line->posting_date
            );
        }

        return $line->quantity * $unitCost;
    }

    private function createItemLedgerEntry(ProductionJournalLine $line, string $direction): ItemLedgerEntry
    {
        $unitCost = (float) ($line->unit_cost ?? 0);
        if ($unitCost === 0.0 && $line->item) {
            $unitCost = $this->costingService->getUnitCost(
                item: $line->item,
                location: $line->location,
                lotNo: $line->lot_no,
                asOfDate: $line->posting_date
            );
        }

        $qty = $direction === 'positive' ? (float) $line->quantity : -(float) $line->quantity;
        $totalCost = $unitCost * abs($qty);

        // Resolve entry type
        $entryType = match ($line->entry_type) {
            ProductionJournalEntryType::Consumption => ItemLedgerEntryType::CONSUMPTION,
            ProductionJournalEntryType::Output => ItemLedgerEntryType::OUTPUT,
            default => ItemLedgerEntryType::CONSUMPTION,
        };

        return ItemLedgerEntry::create([
            'item_id' => $line->item_id,
            'posting_date' => $line->posting_date,
            'entry_type' => $entryType,
            'document_number' => $line->production_order_no,
            'document_line_number' => $line->line_no,
            'location_id' => $line->location_id,
            'bin_code' => $line->bin_id ? Bin::find($line->bin_id)?->code : null,
            'quantity' => $qty,
            'cost_amount_actual' => $totalCost,
            'lot_number' => $line->lot_no,
            'serial_number' => $line->serial_no,
            'expiration_date' => $line->expiration_date,
            'general_product_posting_group_id' => $line->item?->general_product_posting_group_id,
            'inventory_posting_group_id' => $line->item?->inventory_posting_group_id,
            'entry_date' => now(),
            'open' => $direction === 'positive',
            'remaining_quantity' => $direction === 'positive' ? $qty : 0,
            'source_type' => ProductionOrder::class,
            'source_id' => $line->production_order_id,
        ]);
    }

    private function postWIPCost(ProductionJournalLine $line, float $cost): void
    {
        // GL Entry: Debit WIP, Credit Inventory
        $this->createGeneralLedgerEntry([
            'chart_of_account_id' => $this->resolveWipAccountId($line),
            'posting_date' => $line->posting_date,
            'document_number' => $line->production_order_no,
            'document_type' => 'PRODUCTION_JOURNAL',
            'document_date' => $line->posting_date,
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
            'chart_of_account_id' => $this->resolveInventoryAccountId($line),
            'posting_date' => $line->posting_date,
            'document_number' => $line->production_order_no,
            'document_type' => 'PRODUCTION_JOURNAL',
            'document_date' => $line->posting_date,
            'description' => "Output: {$line->item->description}",
            'debit_amount' => $cost,
            'credit_amount' => 0,
            'amount_lcy' => $cost,
        ]);

        $this->createGeneralLedgerEntry([
            'chart_of_account_id' => $this->resolveWipAccountId($line),
            'posting_date' => $line->posting_date,
            'document_number' => $line->production_order_no,
            'document_type' => 'PRODUCTION_JOURNAL',
            'document_date' => $line->posting_date,
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

    protected function updateLineStatus(object $line, string $status, ?int $postedEntryId = null, ?string $postedEntryType = null): void
    {
        $data = ['line_status' => $status];
        if ($postedEntryType === ItemLedgerEntry::class) {
            $data['item_ledger_entry_id'] = $postedEntryId;
        } elseif ($postedEntryType === CapacityLedgerEntry::class) {
            $data['capacity_ledger_entry_id'] = $postedEntryId;
        }

        $line->update($data);
    }

    private function resolveWipAccountId(ProductionJournalLine $line): ?int
    {
        // If the line has wip_account_id set, use it.
        if ($line->wip_account_id) {
            return (int) $line->wip_account_id;
        }

        // Try getting it from production order's inventory posting setup.
        $postingGroupId = $line->productionOrder->inventory_posting_group_id;
        if ($postingGroupId) {
            $setup = InventoryPostingSetup::getFor((int) $postingGroupId, $line->location_id);
            if ($setup && $setup->wip_account_id) {
                return (int) $setup->wip_account_id;
            }
        }

        // Try template default
        if ($line->batch?->template?->default_wip_account_id) {
            return (int) $line->batch->template->default_wip_account_id;
        }

        return null;
    }

    private function resolveInventoryAccountId(ProductionJournalLine $line): ?int
    {
        // If the line has inventory_account_id set, use it.
        if ($line->inventory_account_id) {
            return (int) $line->inventory_account_id;
        }

        // Try getting it from item's inventory posting setup.
        $item = $line->item;
        if ($item && $item->inventory_posting_group_id) {
            $setup = InventoryPostingSetup::getFor((int) $item->inventory_posting_group_id, $line->location_id);
            if ($setup && $setup->inventory_account_id) {
                return (int) $setup->inventory_account_id;
            }
        }

        return null;
    }
}
