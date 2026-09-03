<?php

namespace App\Services\Dashboard;

use App\Enums\ItemLedgerEntryType;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\ValueEntry;
use App\Services\Business\BusinessContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ManufacturingDashboardService
{
    public function __construct(private readonly BusinessContextService $businessContext) {}

    private ?int $businessId = null;

    /**
     * @return array<string, mixed>
     */
    public function summary(?Carbon $startDate = null, ?Carbon $endDate = null, ?int $businessId = null): array
    {
        $startDate ??= now()->startOfMonth();
        $endDate ??= now();
        $businessId = $this->businessContext->resolveId($businessId);
        $this->businessId = $businessId;

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'open_production_orders' => ProductionOrder::query()
                ->whereIn('status', ['PLANNED', 'FIRM_PLANNED', 'RELEASED'])
                ->when($this->businessId !== null, fn ($query) => $query->where('business_id', $this->businessId))
                ->count(),
            'wip_value' => round($this->wipValue(), 2),
            'output_quantity' => round($this->outputQuantity($startDate, $endDate), 4),
            'component_shortages' => $this->componentShortages(),
            'production_variance' => round($this->productionVariance($startDate, $endDate), 2),
            'ownership_limitations' => $businessId !== null
                ? ['production_orders', 'item_ledger_entries']
                : [],
            'reconciliation_scope' => 'not_applicable',
        ];
    }

    private function wipValue(): float
    {
        return (float) ValueEntry::query()
            ->whereNotNull('production_order_no')
            ->when($this->businessId !== null, fn ($query) => $query->where('business_id', $this->businessId))
            ->sum(DB::raw('CASE WHEN item_ledger_entry_type = 7 THEN -ABS(cost_amount_actual) ELSE cost_amount_actual END'));
    }

    private function outputQuantity(Carbon $startDate, Carbon $endDate): float
    {
        return (float) ItemLedgerEntry::query()
            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($this->businessId !== null, fn ($query) => $query->where('business_id', $this->businessId))
            ->sum('quantity');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function componentShortages(): array
    {
        return ProductionOrderComponent::query()
            ->join('production_orders', 'production_orders.id', '=', 'production_order_components.production_order_id')
            ->join('items', 'items.id', '=', 'production_order_components.item_id')
            ->leftJoin('item_ledger_entries as ile', function ($join): void {
                $join->on('ile.item_id', '=', 'production_order_components.item_id');
                if ($this->businessId !== null) {
                    $join->where('ile.business_id', $this->businessId);
                }
            })
            ->where('production_order_components.remaining_quantity', '>', 0)
            ->when($this->businessId !== null, fn ($query) => $query->where('production_orders.business_id', $this->businessId))
            ->groupBy(
                'production_order_components.id',
                'production_order_components.production_order_id',
                'production_order_components.item_id',
                'production_order_components.remaining_quantity',
                'production_orders.business_id',
                'items.item_code',
                'items.description'
            )
            ->havingRaw('COALESCE(SUM(ile.quantity), 0) < production_order_components.remaining_quantity')
            ->orderBy('items.item_code')
            ->limit(10)
            ->get([
                'production_order_components.id as component_id',
                'production_order_components.production_order_id',
                'production_order_components.item_id',
                'items.item_code',
                'items.description',
                'production_order_components.remaining_quantity',
                DB::raw('COALESCE(SUM(ile.quantity), 0) as available_quantity'),
            ])
            ->map(fn ($row): array => [
                'component_id' => (int) $row->component_id,
                'production_order_id' => (int) $row->production_order_id,
                'item_id' => (int) $row->item_id,
                'item_code' => (string) $row->item_code,
                'description' => (string) $row->description,
                'remaining_quantity' => round((float) $row->remaining_quantity, 4),
                'available_quantity' => round((float) $row->available_quantity, 4),
                'shortage_quantity' => round(max(0, (float) $row->remaining_quantity - (float) $row->available_quantity), 4),
            ])
            ->all();
    }

    private function productionVariance(Carbon $startDate, Carbon $endDate): float
    {
        return (float) ValueEntry::query()
            ->whereNotNull('production_order_no')
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($this->businessId !== null, fn ($query) => $query->where('business_id', $this->businessId))
            ->sum('variance_amount');
    }
}
