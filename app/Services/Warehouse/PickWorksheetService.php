<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\WarehouseActivityType;
use App\Enums\WarehouseDocumentStatus;
use App\Models\Bin;
use App\Models\Item;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\WarehouseActivity;
use App\Models\WarehouseActivityLine;
use App\Models\WarehouseRequest;
use App\Services\NumberSeriesService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PickWorksheetService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService,
        private readonly BinAvailabilityService $binService,
        private readonly WarehouseReservationService $reservationService
    ) {}

    /**
     * Create pick from production order component
     */
    public function createPickFromProductionOrder(
        ProductionOrderComponent $component,
        ?string $toBinCode = null
    ): ?WarehouseActivity {
        return DB::transaction(function () use ($component, $toBinCode) {
            // Check if component needs picking
            $remainingQty = $component->remaining_quantity;
            if ($remainingQty <= 0) {
                return null;
            }

            $item = $component->item;
            $location = $component->productionOrder->location;

            // Determine destination bin
            $toBin = $this->resolveProductionToBin($component, $toBinCode);

            // Find available inventory to pick
            $pickSources = $this->binService->findPickSources(
                item: $item,
                location: $location,
                quantityNeeded: $remainingQty,
                lotNo: $component->lot_no,
                excludeBins: [$toBin?->id]
            );

            if ($pickSources->isEmpty()) {
                throw new \RuntimeException("Insufficient inventory for item {$item->item_no} at location {$location->code}");
            }

            // Create warehouse activity
            $activity = WarehouseActivity::create([
                'no' => $this->numberSeriesService->getNextNo('PICK'),
                'activity_type' => WarehouseActivityType::PICK,
                'status' => WarehouseDocumentStatus::OPEN,
                'location_id' => $location->id,
                'source_document' => 'production_order',
                'source_no' => $component->productionOrder->document_number,
                'source_line_no' => $component->line_number,
                'source_id' => $component->id,
            ]);

            // Create activity lines
            $lineNo = 10000;
            $remainingToPick = $remainingQty;

            foreach ($pickSources as $source) {
                $pickQty = min($source->available_quantity, $remainingToPick);

                WarehouseActivityLine::create([
                    'warehouse_activity_id' => $activity->id,
                    'line_no' => $lineNo,
                    'item_id' => $item->id,
                    'quantity_to_handle' => $pickQty,
                    'quantity_base' => $this->calculateBaseQty($item, $pickQty, $component->unit_of_measure_code),
                    'unit_of_measure_code' => $component->unit_of_measure_code,
                    'source_zone_id' => $source->zone_id,
                    'source_bin_id' => $source->bin_id,
                    'source_lot_no' => $source->lot_no,
                    'source_serial_no' => $source->serial_no,
                    'destination_zone_id' => $toBin?->zone_id,
                    'destination_bin_id' => $toBin?->id,
                    'lot_no' => $source->lot_no,
                    'serial_no' => $source->serial_no,
                    'expiration_date' => $source->expiration_date,
                ]);

                // Reserve inventory
                $this->reservationService->reserve(
                    binContent: $source,
                    quantity: $pickQty,
                    reservationType: 'pick',
                    referenceNo: $activity->no,
                    referenceLineNo: $lineNo
                );

                $remainingToPick -= $pickQty;
                $lineNo += 10000;

                if ($remainingToPick <= 0) {
                    break;
                }
            }

            // Create warehouse request
            WarehouseRequest::create([
                'source_document' => 'production_order',
                'source_no' => $component->productionOrder->document_number,
                'source_line_no' => $component->line_number,
                'source_id' => $component->id,
                'request_type' => 'pick',
                'location_id' => $location->id,
                'zone_id' => $toBin?->zone_id,
                'bin_id' => $toBin?->id,
                'item_id' => $item->id,
                'quantity' => $remainingQty - $remainingToPick,
                'quantity_base' => $this->calculateBaseQty($item, $remainingQty - $remainingToPick, $component->unit_of_measure_code),
                'unit_of_measure_code' => $component->unit_of_measure_code,
                'quantity_outstanding' => $remainingQty - $remainingToPick,
                'lot_no' => $component->lot_no,
                'warehouse_activity_id' => $activity->id,
            ]);

            return $activity;
        });
    }

    /**
     * Create picks for all components of a production order
     */
    public function createPicksForProductionOrder(ProductionOrder $order): Collection
    {
        $activities = collect();

        foreach ($order->components()->where('remaining_quantity', '>', 0)->get() as $component) {
            try {
                $activity = $this->createPickFromProductionOrder($component);
                if ($activity) {
                    $activities->push($activity);
                }
            } catch (\RuntimeException $e) {
                // Log insufficient inventory
                \Log::warning("Cannot pick for component {$component->line_number}: {$e->getMessage()}");
            }
        }

        return $activities;
    }

    private function resolveProductionToBin(ProductionOrderComponent $component, ?string $toBinCode): ?Bin
    {
        if ($toBinCode) {
            return Bin::where('location_id', $component->productionOrder->location_id)
                ->where('bin_code', $toBinCode)
                ->first();
        }

        // Use work center default bin
        $workCenter = $component->routingLine?->workCenter;
        if ($workCenter) {
            $workCenterBin = $workCenter->workCenterBin;

            return $workCenterBin?->toProductionBin;
        }

        // Fallback to location default production bin
        return null; // Will be handled by location default
    }

    private function calculateBaseQty(Item $item, float $qty, string $uom): float
    {
        $uomRecord = $item->unitOfMeasures()->where('code', $uom)->first();

        return $qty * ($uomRecord?->qty_per_base_unit ?? 1);
    }
}
