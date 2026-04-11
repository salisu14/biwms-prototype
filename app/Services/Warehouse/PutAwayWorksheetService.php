<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\BinType;
use App\Enums\ZoneType;
use App\Enums\WarehouseActivityType;
use App\Enums\WarehouseDocumentStatus;
use App\Models\Bin;
use App\Models\Item;
use App\Models\Manufacturing\ProductionOrderLine;
use App\Models\PurchaseOrderLine;
use App\Models\WarehouseActivity;
use App\Models\WarehouseActivityLine;
use App\Services\NumberSeriesService;
use Illuminate\Support\Facades\DB;

class PutAwayWorksheetService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService,
        private readonly BinSuggestionService $binSuggestionService
    ) {}

    /**
     * Create put-away from production order output
     */
    public function createPutAwayFromProductionOutput(
        ProductionOrderLine $outputLine,
        float $quantity,
        ?string $fromBinCode = null
    ): WarehouseActivity {
        return DB::transaction(function () use ($outputLine, $quantity, $fromBinCode) {
            $item = $outputLine->item;
            $location = $outputLine->productionOrder->location;

            // Source is production output area
            $fromBin = $this->resolveProductionFromBin($outputLine, $fromBinCode);

            // Suggest put-away bins
            $suggestedBins = $this->binSuggestionService->suggestPutAwayBins(
                item: $item,
                location: $location,
                quantity: $quantity,
                preferredBinType: BinType::STORAGE,
                preferredZoneType: ZoneType::STORAGE
            );

            $activity = WarehouseActivity::create([
                'no' => $this->numberSeriesService->getNextNo('PUTAWAY'),
                'activity_type' => WarehouseActivityType::PUT_AWAY,
                'status' => WarehouseDocumentStatus::OPEN,
                'location_id' => $location->id,
                'zone_id' => $fromBin?->zone_id,
                'bin_id' => $fromBin?->id,
                'source_document' => 'production_order',
                'source_no' => $outputLine->productionOrder->document_number,
                'source_line_no' => $outputLine->line_number,
                'source_id' => $outputLine->id,
            ]);

            $lineNo = 10000;
            $remainingQty = $quantity;

            foreach ($suggestedBins as $suggestion) {
                $putQty = min($suggestion->available_capacity, $remainingQty);

                WarehouseActivityLine::create([
                    'warehouse_activity_id' => $activity->id,
                    'line_no' => $lineNo,
                    'item_id' => $item->id,
                    'quantity_to_handle' => $putQty,
                    'quantity_base' => $this->calculateBaseQty($item, $putQty, $outputLine->unit_of_measure_code),
                    'unit_of_measure_code' => $outputLine->unit_of_measure_code,
                    'source_zone_id' => $fromBin?->zone_id,
                    'source_bin_id' => $fromBin?->id,
                    'destination_zone_id' => $suggestion->zone_id,
                    'destination_bin_id' => $suggestion->bin_id,
                    'lot_no' => $outputLine->lot_no,
                    'serial_no' => $outputLine->serial_no,
                    'expiration_date' => $outputLine->expiration_date,
                ]);

                $remainingQty -= $putQty;
                $lineNo += 10000;

                if ($remainingQty <= 0) {
                    break;
                }
            }

            return $activity;
        });
    }

    /**
     * Create put-away from purchase receipt
     */
    public function createPutAwayFromPurchase(
        PurchaseOrderLine $line,
        float $receivedQty,
        ?string $receivingBinCode = null
    ): WarehouseActivity {
        return DB::transaction(function () use ($line, $receivedQty, $receivingBinCode) {
            $item = $line->item;
            $location = $line->purchaseOrder->location;

            // Source is receiving bin
            $fromBin = $receivingBinCode
                ? Bin::where('location_id', $location->id)->where('bin_code', $receivingBinCode)->first()
                : $location->receiving_bin;

            // Suggest put-away bins
            $suggestedBins = $this->binSuggestionService->suggestPutAwayBins(
                item: $item,
                location: $location,
                quantity: $receivedQty,
                preferredBinType: BinType::STORAGE,
                preferredZoneType: ZoneType::STORAGE
            );

            $activity = WarehouseActivity::create([
                'no' => $this->numberSeriesService->getNextNo('PUTAWAY'),
                'activity_type' => WarehouseActivityType::PUT_AWAY,
                'status' => WarehouseDocumentStatus::OPEN,
                'location_id' => $location->id,
                'source_document' => 'purchase_order',
                'source_no' => $line->purchaseOrder->order_number,
                'source_line_no' => $line->line_number,
                'source_id' => $line->id,
            ]);

            $lineNo = 10000;
            $remainingQty = $receivedQty;

            foreach ($suggestedBins as $suggestion) {
                $putQty = min($suggestion->available_capacity, $remainingQty);

                WarehouseActivityLine::create([
                    'warehouse_activity_id' => $activity->id,
                    'line_no' => $lineNo,
                    'item_id' => $item->id,
                    'quantity_to_handle' => $putQty,
                    'quantity_base' => $this->calculateBaseQty($item, $putQty, $line->unit_of_measure),
                    'unit_of_measure_code' => $line->unit_of_measure,
                    'source_zone_id' => $fromBin?->zone_id,
                    'source_bin_id' => $fromBin?->id,
                    'destination_zone_id' => $suggestion->zone_id,
                    'destination_bin_id' => $suggestion->bin_id,
                    'lot_no' => $line->lot_no,
                    'serial_no' => $line->serial_no,
                ]);

                $remainingQty -= $putQty;
                $lineNo += 10000;

                if ($remainingQty <= 0) {
                    break;
                }
            }

            return $activity;
        });
    }

    private function resolveProductionFromBin(ProductionOrderLine $line, ?string $fromBinCode): ?Bin
    {
        if ($fromBinCode) {
            return Bin::where('location_id', $line->productionOrder->location_id)
                ->where('bin_code', $fromBinCode)
                ->first();
        }

        $workCenter = $line->routingLine?->workCenter;
        if ($workCenter) {
            return $workCenter->workCenterBin?->fromProductionBin;
        }

        return null;
    }

    private function calculateBaseQty(Item $item, float $qty, string $uom): float
    {
        $uomRecord = $item->unitOfMeasures()->where('code', $uom)->first();

        return $qty * ($uomRecord?->qty_per_base_unit ?? 1);
    }
}
