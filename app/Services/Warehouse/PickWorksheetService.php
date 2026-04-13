<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\WarehouseDocumentStatus;
use App\Models\Bin;
use App\Models\Item;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\WarehousePick;
use App\Models\WarehousePickLine;
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
     * Create a WarehousePick from a production order component.
     */
    public function createPickFromProductionOrder(
        ProductionOrderComponent $component,
        ?string $toBinCode = null
    ): ?WarehousePick {
        return DB::transaction(function () use ($component, $toBinCode): ?WarehousePick {
            $remainingQty = $component->remaining_quantity;

            if ($remainingQty <= 0) {
                return null;
            }

            $item = $component->item;
            $location = $component->productionOrder->location;
            $toBin = $this->resolveProductionToBin($component, $toBinCode);

            $pickSources = $this->binService->findPickSources(
                item: $item,
                location: $location,
                quantityNeeded: $remainingQty,
                lotNo: $component->lot_no,
                excludeBins: [$toBin?->id]
            );

            if ($pickSources->isEmpty()) {
                throw new \RuntimeException(
                    "Insufficient inventory for item {$item->item_no} at location {$location->code}"
                );
            }

            $pick = WarehousePick::create([
                'no' => $this->numberSeriesService->getNextNo('PICK'),
                'status' => WarehouseDocumentStatus::OPEN,
                'location_id' => $location->id,
                'source_document' => 'production_order',
                'source_no' => $component->productionOrder->document_number,
                'source_id' => $component->id,
            ]);

            $lineNo = 10000;
            $remainingToPick = $remainingQty;

            foreach ($pickSources as $source) {
                $pickQty = min($source->available_quantity, $remainingToPick);

                WarehousePickLine::create([
                    'warehouse_pick_id' => $pick->id,
                    'line_no' => $lineNo,
                    'source_line_no' => $component->line_number,
                    'item_id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $pickQty,
                    'quantity_to_handle' => $pickQty,
                    'quantity_handled' => 0,
                    'quantity_base' => $this->calculateBaseQty($item, $pickQty, $component->unit_of_measure_code),
                    'unit_of_measure_code' => $component->unit_of_measure_code,
                    'zone_id' => $source->zone_id,
                    'bin_id' => $source->bin_id,
                    'lot_no' => $source->lot_no,
                    'serial_no' => $source->serial_no,
                    'expiration_date' => $source->expiration_date,
                    'destination_zone_id' => $toBin?->zone_id,
                    'destination_bin_id' => $toBin?->id,
                    'line_status' => 'open',
                ]);

                $this->reservationService->reserve(
                    binContent: $source,
                    quantity: $pickQty,
                    reservationType: 'pick',
                    referenceNo: $pick->no,
                    referenceLineNo: $lineNo
                );

                $remainingToPick -= $pickQty;
                $lineNo += 10000;

                if ($remainingToPick <= 0) {
                    break;
                }
            }

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
            ]);

            return $pick;
        });
    }

    /**
     * Create picks for all outstanding components of a production order.
     */
    public function createPicksForProductionOrder(ProductionOrder $order): Collection
    {
        $picks = collect();

        foreach ($order->components()->where('remaining_quantity', '>', 0)->get() as $component) {
            try {
                $pick = $this->createPickFromProductionOrder($component);

                if ($pick) {
                    $picks->push($pick);
                }
            } catch (\RuntimeException $e) {
                \Log::warning("Cannot pick for component {$component->line_number}: {$e->getMessage()}");
            }
        }

        return $picks;
    }

    private function resolveProductionToBin(ProductionOrderComponent $component, ?string $toBinCode): ?Bin
    {
        if ($toBinCode) {
            return Bin::where('location_id', $component->productionOrder->location_id)
                ->where('bin_code', $toBinCode)
                ->first();
        }

        $workCenter = $component->routingLine?->workCenter;

        if ($workCenter) {
            return $workCenter->workCenterBin?->toProductionBin;
        }

        return null;
    }

    private function calculateBaseQty(Item $item, float $qty, string $uom): float
    {
        $uomRecord = $item->unitOfMeasures()->where('code', $uom)->first();

        return $qty * ($uomRecord?->qty_per_base_unit ?? 1);
    }
}
