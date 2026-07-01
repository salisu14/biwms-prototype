<?php

namespace App\Services\Dashboard;

use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryDashboardService
{
    public function __construct(
        private readonly ReconciliationWarningService $reconciliationWarningService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate ??= now()->startOfMonth();
        $endDate ??= now();
        $inventoryWarnings = $this->reconciliationWarningService->inventoryWarnings();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'stock_quantity' => round($this->stockQuantity(), 4),
            'stock_value' => round($this->stockValue(), 2),
            'negative_stock_count' => $this->negativeStockCount(),
            'stock_mismatch_warnings' => $inventoryWarnings['sections']['stock_mismatches'] ?? 0,
            'reconciliation_warnings' => $inventoryWarnings,
            'top_moving_items' => $this->topMovingItems($startDate, $endDate),
            'low_stock_items' => $this->lowStockItems(),
            'expiring_items' => $this->expiringItems(),
        ];
    }

    private function stockQuantity(): float
    {
        return (float) ItemLedgerEntry::query()->sum('quantity');
    }

    private function stockValue(): float
    {
        return (float) ValueEntry::query()
            ->selectRaw($this->inventoryValueEffectSql().' as stock_value')
            ->value('stock_value');
    }

    private function negativeStockCount(): int
    {
        return DB::query()
            ->fromSub(
                ItemLedgerEntry::query()
                    ->select('item_id', 'location_id', 'lot_number', 'serial_number')
                    ->selectRaw('COALESCE(SUM(quantity), 0) as quantity')
                    ->groupBy('item_id', 'location_id', 'lot_number', 'serial_number'),
                'stock'
            )
            ->where('quantity', '<', 0)
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topMovingItems(Carbon $startDate, Carbon $endDate): array
    {
        return ItemLedgerEntry::query()
            ->join('items', 'items.id', '=', 'item_ledger_entries.item_id')
            ->whereBetween('item_ledger_entries.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('items.id', 'items.item_code', 'items.description')
            ->orderByDesc(DB::raw('SUM(ABS(item_ledger_entries.quantity))'))
            ->limit(5)
            ->get([
                'items.id as item_id',
                'items.item_code',
                'items.description',
                DB::raw('SUM(ABS(item_ledger_entries.quantity)) as movement_quantity'),
            ])
            ->map(fn ($row): array => [
                'item_id' => (int) $row->item_id,
                'item_code' => (string) $row->item_code,
                'description' => (string) $row->description,
                'movement_quantity' => round((float) $row->movement_quantity, 4),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lowStockItems(): array
    {
        return Item::query()
            ->whereNotNull('reorder_point')
            ->where('reorder_point', '>', 0)
            ->leftJoin('item_ledger_entries as ile', 'ile.item_id', '=', 'items.id')
            ->groupBy('items.id', 'items.item_code', 'items.description', 'items.reorder_point')
            ->havingRaw('COALESCE(SUM(ile.quantity), 0) <= items.reorder_point')
            ->orderBy('items.item_code')
            ->limit(10)
            ->get([
                'items.id as item_id',
                'items.item_code',
                'items.description',
                'items.reorder_point',
                DB::raw('COALESCE(SUM(ile.quantity), 0) as ledger_quantity'),
            ])
            ->map(fn ($row): array => [
                'item_id' => (int) $row->item_id,
                'item_code' => (string) $row->item_code,
                'description' => (string) $row->description,
                'ledger_quantity' => round((float) $row->ledger_quantity, 4),
                'reorder_point' => round((float) $row->reorder_point, 4),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expiringItems(): array
    {
        return ItemLedgerEntry::query()
            ->join('items', 'items.id', '=', 'item_ledger_entries.item_id')
            ->whereNotNull('item_ledger_entries.expiration_date')
            ->whereBetween('item_ledger_entries.expiration_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->where('item_ledger_entries.remaining_quantity', '>', 0)
            ->orderBy('item_ledger_entries.expiration_date')
            ->limit(10)
            ->get([
                'items.id as item_id',
                'items.item_code',
                'items.description',
                'item_ledger_entries.lot_number',
                'item_ledger_entries.serial_number',
                'item_ledger_entries.expiration_date',
                'item_ledger_entries.remaining_quantity',
            ])
            ->map(fn ($row): array => [
                'item_id' => (int) $row->item_id,
                'item_code' => (string) $row->item_code,
                'description' => (string) $row->description,
                'lot_number' => $row->lot_number,
                'serial_number' => $row->serial_number,
                'expiration_date' => Carbon::parse($row->expiration_date)->toDateString(),
                'remaining_quantity' => round((float) $row->remaining_quantity, 4),
            ])
            ->all();
    }

    private function inventoryValueEffectSql(string $table = 'value_entries'): string
    {
        return "COALESCE(SUM(CASE WHEN {$table}.item_ledger_entry_type IN (2, 4, 6, 9) THEN -ABS({$table}.cost_amount_actual) ELSE {$table}.cost_amount_actual END), 0)";
    }
}
