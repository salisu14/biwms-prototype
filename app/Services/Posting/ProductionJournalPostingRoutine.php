<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionJournalEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\Bin;
use App\Models\CapacityLedgerEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ProductionJournalLine;
use App\Services\Inventory\CostingService;
use App\Services\Inventory\ItemApplicationService;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
use App\Services\Inventory\ValueEntryService;

class ProductionJournalPostingRoutine extends AbstractJournalPostingRoutine
{
    public function __construct(
        private readonly ItemJournalPostingRoutine $itemPostingRoutine,
        private readonly CostingService $costingService
    ) {}

    public function post(object $batch): PostingResult
    {
        $status = is_string($batch->status) ? $batch->status : ($batch->status->value ?? null);

        if ($status === 'posted') {
            return new PostingResult(true, [], []);
        }

        return parent::post($batch);
    }

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
        if ($this->lineAlreadyPosted($line)) {
            return;
        }

        match ($line->entry_type) {
            ProductionJournalEntryType::Consumption => $this->postConsumption($line),
            ProductionJournalEntryType::Output => $this->postOutput($line),
            ProductionJournalEntryType::Capacity => $this->postCapacity($line),
            ProductionJournalEntryType::Scrap => $this->postScrap($line),
        };
    }

    private function postConsumption(ProductionJournalLine $line): void
    {
        if ($this->reuseExistingItemLedgerEntry($line)) {
            return;
        }

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

        app(ItemApplicationService::class)->applyOutbound($itemLedgerEntry, 'production_journal_consumption', strict: false);
        app(ValueEntryService::class)->ensureForItemLedgerEntry($itemLedgerEntry);
        app(ValueEntryAccountingOrchestrator::class)->postForItemLedgerEntry($itemLedgerEntry);

        $this->updateLineStatus($line, 'posted', $itemLedgerEntry->id, ItemLedgerEntry::class);
    }

    private function postOutput(ProductionJournalLine $line): void
    {
        if ($this->reuseExistingItemLedgerEntry($line)) {
            return;
        }

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

        app(ValueEntryService::class)->ensureForItemLedgerEntry($itemLedgerEntry);
        app(ValueEntryAccountingOrchestrator::class)->postForItemLedgerEntry($itemLedgerEntry);

        $this->updateLineStatus($line, 'posted', $itemLedgerEntry->id, ItemLedgerEntry::class);
    }

    private function postCapacity(ProductionJournalLine $line): void
    {
        if ($this->reuseExistingCapacityLedgerEntry($line)) {
            return;
        }

        $workCenter = $line->workCenter;

        // Calculate costs
        $directCost = $this->calculateDirectCost($line, $workCenter);
        $overheadCost = $line->batch->template->absorb_overhead
            ? $this->calculateOverhead($line, $workCenter)
            : 0;
        $idempotencyKey = hash('sha256', implode('|', [
            'production-journal-capacity',
            $line->id,
            $line->batch_id,
            $line->line_no,
            $line->production_order_id,
            $line->entry_type?->value ?? (string) $line->entry_type,
        ]));

        $existingCapacityEntry = CapacityLedgerEntry::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingCapacityEntry) {
            app(ValueEntryService::class)->ensureForCapacityLedgerEntry($existingCapacityEntry, $line->created_by);
            app(ValueEntryAccountingOrchestrator::class)->postForCapacityLedgerEntry($existingCapacityEntry);
            $this->updateLineStatus($line, 'posted', $existingCapacityEntry->id, CapacityLedgerEntry::class);

            return;
        }

        $capacityEntry = CapacityLedgerEntry::create([
            'business_id' => $line->productionOrder?->business_id,
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
            'cost_state' => 'actual',
            'idempotency_key' => $idempotencyKey,
            'costing_metadata' => [
                'phase_1d_idempotent_capacity_posting' => true,
                'source' => 'production_journal_line',
                'production_journal_line_id' => $line->id,
                'batch_id' => $line->batch_id,
                'line_no' => $line->line_no,
            ],
        ]);

        app(ValueEntryService::class)->ensureForCapacityLedgerEntry($capacityEntry, $line->created_by);
        app(ValueEntryAccountingOrchestrator::class)->postForCapacityLedgerEntry($capacityEntry);

        if ($line->routingLine) {
            $line->routingLine->actual_setup_time = (float) $line->routingLine->actual_setup_time + (float) ($line->setup_time ?? 0);
            $line->routingLine->actual_run_time = (float) $line->routingLine->actual_run_time + (float) ($line->run_time ?? 0);
            $line->routingLine->status = $line->routingLine->actual_run_time >= (float) $line->routingLine->run_time
                ? 'COMPLETED'
                : 'IN_PROGRESS';
            $line->routingLine->save();
        }

        $this->updateLineStatus($line, 'posted', $capacityEntry->id, CapacityLedgerEntry::class);
    }

    private function postScrap(ProductionJournalLine $line): void
    {
        if ($this->reuseExistingItemLedgerEntry($line)) {
            return;
        }

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

        app(ValueEntryService::class)->ensureForItemLedgerEntry($itemLedgerEntry);
        app(ValueEntryAccountingOrchestrator::class)->postForItemLedgerEntry($itemLedgerEntry);

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
                asOfDate: $this->postingDateString($line)
            );
        }

        return $line->quantity * $unitCost;
    }

    private function createItemLedgerEntry(ProductionJournalLine $line, string $direction): ItemLedgerEntry
    {
        $businessId = $line->productionOrder?->business_id;
        $unitCost = (float) ($line->unit_cost ?? 0);
        if ($unitCost === 0.0 && $line->item) {
            $unitCost = $this->costingService->getUnitCost(
                item: $line->item,
                location: $line->location,
                lotNo: $line->lot_no,
                asOfDate: $this->postingDateString($line)
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
            'business_id' => $businessId,
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

    private function postingDateString(ProductionJournalLine $line): ?string
    {
        if ($line->posting_date === null) {
            return null;
        }

        if (is_string($line->posting_date)) {
            return $line->posting_date;
        }

        return $line->posting_date->toDateString();
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

    private function lineAlreadyPosted(ProductionJournalLine $line): bool
    {
        $status = is_string($line->line_status) ? $line->line_status : ($line->line_status?->value ?? null);

        if ($status !== 'posted') {
            return false;
        }

        if ($line->item_ledger_entry_id) {
            return $this->reuseExistingItemLedgerEntry($line);
        }

        if ($line->capacity_ledger_entry_id) {
            return $this->reuseExistingCapacityLedgerEntry($line);
        }

        return true;
    }

    private function reuseExistingItemLedgerEntry(ProductionJournalLine $line): bool
    {
        if (! $line->item_ledger_entry_id) {
            return false;
        }

        $itemLedgerEntry = ItemLedgerEntry::query()->find($line->item_ledger_entry_id);

        if (! $itemLedgerEntry) {
            return false;
        }

        if ($line->entry_type === ProductionJournalEntryType::Consumption || $line->entry_type === ProductionJournalEntryType::Scrap) {
            app(ItemApplicationService::class)->applyOutbound($itemLedgerEntry, 'production_journal_consumption', strict: false);
        }

        app(ValueEntryService::class)->ensureForItemLedgerEntry($itemLedgerEntry);
        app(ValueEntryAccountingOrchestrator::class)->postForItemLedgerEntry($itemLedgerEntry);
        $this->updateLineStatus($line, 'posted', $itemLedgerEntry->id, ItemLedgerEntry::class);

        return true;
    }

    private function reuseExistingCapacityLedgerEntry(ProductionJournalLine $line): bool
    {
        if (! $line->capacity_ledger_entry_id) {
            return false;
        }

        $capacityLedgerEntry = CapacityLedgerEntry::query()->find($line->capacity_ledger_entry_id);

        if (! $capacityLedgerEntry) {
            return false;
        }

        app(ValueEntryService::class)->ensureForCapacityLedgerEntry($capacityLedgerEntry, $line->created_by);
        app(ValueEntryAccountingOrchestrator::class)->postForCapacityLedgerEntry($capacityLedgerEntry);
        $this->updateLineStatus($line, 'posted', $capacityLedgerEntry->id, CapacityLedgerEntry::class);

        return true;
    }
}
