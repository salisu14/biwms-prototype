<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Models\CapacityLedgerEntry;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ValueEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProductionInventoryOwnershipAuditService
{
    /** @return array<string, mixed> */
    public function report(): array
    {
        $productionOrders = ProductionOrder::query()->whereNull('business_id')->get(['id', 'document_number']);
        $itemLedgerEntries = ItemLedgerEntry::query()->whereNull('business_id')->get(['id', 'entry_number', 'document_number']);
        $capacityEntries = CapacityLedgerEntry::query()->whereNull('business_id')->get(['id', 'production_order_id', 'document_number']);

        $valueEntries = ValueEntry::query()
            ->whereNull('business_id')
            ->where(function ($query): void {
                $query->whereNotNull('source_id')->orWhereNotNull('item_ledger_entry_no');
            })
            ->count();

        $crossBusinessApplications = ItemApplicationEntry::query()
            ->join('item_ledger_entries as outbound', 'outbound.id', '=', 'item_application_entries.outbound_item_ledger_entry_id')
            ->join('item_ledger_entries as inbound', 'inbound.id', '=', 'item_application_entries.inbound_item_ledger_entry_id')
            ->whereNotNull('outbound.business_id')
            ->whereNotNull('inbound.business_id')
            ->whereColumn('outbound.business_id', '!=', 'inbound.business_id')
            ->count();

        $productionItemMismatches = DB::table('item_ledger_entries as ile')
            ->join('production_orders as po', function ($join): void {
                $join->on('po.id', '=', 'ile.source_id')
                    ->where('ile.source_type', ProductionOrder::class);
            })
            ->whereNotNull('po.business_id')
            ->where(function ($query): void {
                $query->whereNull('ile.business_id')->orWhereColumn('ile.business_id', '!=', 'po.business_id');
            })
            ->count();

        $productionCapacityMismatches = DB::table('capacity_ledger_entries as cle')
            ->join('production_orders as po', 'po.id', '=', 'cle.production_order_id')
            ->whereNotNull('po.business_id')
            ->where(function ($query): void {
                $query->whereNull('cle.business_id')->orWhereColumn('cle.business_id', '!=', 'po.business_id');
            })
            ->count();

        $itemValueMismatches = DB::table('item_ledger_entries as ile')
            ->join('value_entries as ve', 've.item_ledger_entry_no', '=', 'ile.entry_number')
            ->whereNotNull('ile.business_id')
            ->where(function ($query): void {
                $query->whereNull('ve.business_id')->orWhereColumn('ve.business_id', '!=', 'ile.business_id');
            })
            ->count();

        $productionValueMismatches = DB::table('production_orders as po')
            ->join('value_entries as ve', 've.production_order_no', '=', 'po.document_number')
            ->whereNotNull('po.business_id')
            ->where(function ($query): void {
                $query->whereNull('ve.business_id')->orWhereColumn('ve.business_id', '!=', 'po.business_id');
            })
            ->count();

        return [
            'production_orders_without_business' => $productionOrders->count(),
            'item_ledger_entries_without_business' => $itemLedgerEntries->count(),
            'capacity_ledger_entries_without_business' => $capacityEntries->count(),
            'value_entries_without_business' => $valueEntries,
            'cross_business_item_applications' => $crossBusinessApplications,
            'production_order_item_ledger_mismatches' => $productionItemMismatches,
            'production_order_capacity_mismatches' => $productionCapacityMismatches,
            'item_ledger_value_entry_mismatches' => $itemValueMismatches,
            'production_order_value_entry_mismatches' => $productionValueMismatches,
            'historical_examples' => [
                'production_orders' => $this->examples($productionOrders),
                'item_ledger_entries' => $this->examples($itemLedgerEntries),
                'capacity_ledger_entries' => $this->examples($capacityEntries),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function examples(Collection $rows): array
    {
        return $rows->take(10)->map(fn ($row): array => [
            'id' => $row->id,
            'document_number' => $row->document_number ?? null,
            'classification' => 'UNKNOWN',
            'reason' => 'No authoritative persisted business owner is available.',
        ])->all();
    }
}
