<?php

namespace App\Services\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Events\ProductionOrderStatusChanged;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use Illuminate\Support\Facades\DB;

class NewProductionOrderService
{
    /*
    |--------------------------------------------------------------------------
    | STATUS MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function changeStatus(ProductionOrder $order, ProductionOrderStatus $newStatus, ?int $userId = null): void
    {
        if (!$order->status->canTransitionTo($newStatus)) {
            throw new \Exception("Invalid status transition");
        }

        if ($newStatus === ProductionOrderStatus::RELEASED) {
            $this->validateForRelease($order);
        }

        if ($newStatus === ProductionOrderStatus::FINISHED) {
            $this->validateForFinish($order);
        }

        $oldStatus = $order->status;

        $order->status = $newStatus;

        if ($newStatus === ProductionOrderStatus::FINISHED) {
            $order->finished_at = now();
            $order->finished_by = $userId;
        }

        $order->save();

        event(new ProductionOrderStatusChanged($order, $oldStatus, $newStatus));
    }

    protected function validateForRelease(ProductionOrder $order): void
    {
        if ($order->components()->count() === 0) {
            throw new \Exception('Production order must have components before release');
        }
    }

    protected function validateForFinish(ProductionOrder $order): void
    {
        if ($order->status !== ProductionOrderStatus::RELEASED) {
            throw new \Exception('Only released production orders can be finished');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REFRESH (BOM + ROUTING)
    |--------------------------------------------------------------------------
    */

    public function refreshOrder(
        ProductionOrder $order,
        bool            $lines = true,
        bool            $routing = true,
        bool            $components = true
    ): void
    {
        if ($order->status === ProductionOrderStatus::FINISHED) {
            throw new \Exception('Cannot refresh finished production order');
        }

        DB::transaction(function () use ($order, $lines, $routing, $components) {
            if ($lines) {
                $this->refreshLines($order);
            }

            if ($routing) {
                $this->refreshRouting($order);
            }

            if ($components) {
                $this->refreshComponents($order);
            }

            $this->schedule($order);
        });
    }

    protected function refreshLines(ProductionOrder $order): void
    {
        if ($order->itemLedgerEntries()->count() === 0) {
            $order->lines()->delete();
        }

        $order->lines()->create([
            'line_number' => 10000,
            'item_id' => $order->item_id,
            'description' => $order->description,
            'quantity' => $order->quantity,
            'unit_of_measure_code' => $order->unit_of_measure_code,
            'quantity_base' => $order->quantity_base,
            'due_date' => $order->due_date,
            'production_bom_id' => $order->production_bom_id,
            'routing_id' => $order->routing_id,
        ]);
    }

    protected function refreshComponents(ProductionOrder $order): void
    {
        if (!$order->production_bom_id) return;

        if ($order->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
                ->count() === 0) {
            $order->components()->delete();
        }

        $bom = $order->productionBom;
        if (!$bom) return;

        $lineNo = 10000;

        foreach ($bom->lines as $bomLine) {
            $expectedQty = $bomLine->quantity_per * $order->quantity;

            $order->components()->create([
                'line_number' => $lineNo,
                'item_id' => $bomLine->item_id,
                'description' => $bomLine->description,
                'unit_of_measure_code' => $bomLine->unit_of_measure_code,
                'quantity_per' => $bomLine->quantity_per,
                'expected_quantity' => $expectedQty,
            ]);

            $lineNo += 10000;
        }
    }

    protected function refreshRouting(ProductionOrder $order): void
    {
        if (!$order->routing_id) return;

        if ($order->capacityLedgerEntries()->count() === 0) {
            $order->routingLines()->delete();
        }

        $routing = $order->routing;
        if (!$routing) return;

        $lineNo = 10000;

        foreach ($routing->lines as $line) {
            $order->routingLines()->create([
                'line_number' => $lineNo,
                'operation_no' => $line->operation_no,
                'run_time' => $line->run_time * $order->quantity,
            ]);

            $lineNo += 10000;
        }
    }

    protected function schedule(ProductionOrder $order): void
    {
        $currentDate = $order->starting_date_time ?? now();

        foreach ($order->routingLines as $line) {
            $line->starting_date_time = $currentDate;
            $line->ending_date_time = $currentDate->copy()->addMinutes($line->total_time_minutes);
            $line->save();

            $currentDate = $line->ending_date_time;
        }

        $order->ending_date_time = $currentDate;
        $order->save();
    }

    /*
    |--------------------------------------------------------------------------
    | POSTING
    |--------------------------------------------------------------------------
    */

    public function postConsumption(ProductionOrder $order, array $consumptions, int $userId): void
    {
        DB::transaction(function () use ($order, $consumptions) {

            foreach ($consumptions as $data) {
                $component = $order->components()->find($data['component_id']);
                if (!$component) continue;

                $qty = $data['quantity'];

                ItemLedgerEntry::create([
                    'entry_type' => ItemLedgerEntryType::CONSUMPTION,
                    'item_id' => $component->item_id,
                    'quantity' => -$qty,
                    'source_id' => $order->id,
                    'source_type' => ProductionOrder::class,
                    'cost_amount_actual' => $qty * $component->item->unit_cost,
                ]);

                $component->increment('actual_quantity_consumed', $qty);
            }
        });
    }

    public function postOutput(ProductionOrder $order, float $quantity): void
    {
        ItemLedgerEntry::create([
            'entry_type' => ItemLedgerEntryType::OUTPUT,
            'item_id' => $order->item_id,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'source_id' => $order->id,
            'source_type' => ProductionOrder::class,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FINISHING
    |--------------------------------------------------------------------------
    */

    public function finish(ProductionOrder $order, int $userId): void
    {
        DB::transaction(function () use ($order, $userId) {

            $totalCost = $this->calculateTotalCost($order);
            $totalOutput = $this->getTotalOutput($order);

            $unitCost = $totalOutput > 0 ? $totalCost / $totalOutput : 0;

            $order->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                ->update([
                    'unit_cost' => $unitCost,
                    'cost_amount_actual' => DB::raw("quantity * {$unitCost}")
                ]);

            $this->changeStatus($order, ProductionOrderStatus::FINISHED, $userId);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATIONS
    |--------------------------------------------------------------------------
    */

    public function calculateTotalCost(ProductionOrder $order): float
    {
        return $order->capacityLedgerEntries()->sum('total_cost') +
            $order->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
                ->sum('cost_amount_actual');
    }

    public function getTotalOutput(ProductionOrder $order): float
    {
        return $order->itemLedgerEntries()
            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ->sum('quantity');
    }
}
