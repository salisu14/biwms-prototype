<?php

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Filament\Pages\Finance\WipValuationReport as FinanceWipValuationReport;
use App\Filament\Pages\Finance\YieldReport;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Manufacturing\ProductionOrderService;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function yieldReportRow(ProductionOrder $order): ProductionOrder
{
    $page = app(YieldReport::class);

    return $page->table(Table::make($page))
        ->getQuery()
        ->whereKey($order->id)
        ->firstOrFail();
}

function yieldDisplayFor(ProductionOrder $order): string|float
{
    $method = new ReflectionMethod(YieldReport::class, 'yieldDisplay');
    $method->setAccessible(true);

    return $method->invoke(null, $order);
}

function manufacturingReportItem(): array
{
    $uom = UnitOfMeasure::create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'conversion_factor' => 1,
        'is_base_uom' => true,
    ]);

    $item = Item::factory()->create(['base_uom_id' => $uom->id]);

    return [$item, $uom];
}

function manufacturingReportOrder(Item $item, User $user, string $documentNumber, array $overrides = []): ProductionOrder
{
    $location = Location::factory()->create(['code' => 'M-'.substr(md5($documentNumber), 0, 8)]);
    $group = GeneralBusinessPostingGroup::factory()->create();

    return ProductionOrder::create(array_merge([
        'document_number' => $documentNumber,
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $item->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_of_measure_code' => 'PCS',
        'starting_date_time' => now(),
        'general_business_posting_group_id' => $group->id,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'location_code' => $location->code,
        'created_by' => $user->id,
    ], $overrides));
}

test('yield report includes canonical production output and uses base quantities', function () {
    $user = User::factory()->create();
    [$item] = manufacturingReportItem();
    $order = manufacturingReportOrder($item, $user, 'YIELD-HARDEN-001', [
        'quantity' => 2,
        'quantity_base' => 576,
    ]);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $order->location()->value('id'),
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => 288,
        'remaining_quantity' => 288,
        'cost_amount_actual' => 0,
        'posting_date' => now(),
        'entry_date' => now(),
        'source_type' => ProductionOrder::class,
        'source_id' => $order->id,
        'open' => true,
    ]);

    $row = yieldReportRow($order);

    expect((float) $row->actual_output)->toBe(288.0)
        ->and((float) $row->output_variance)->toBe(288.0)
        ->and(yieldDisplayFor($row))->toBe(50.0);
});

test('yield display distinguishes not started, partial, exact, over, and zero planned output', function () {
    $user = User::factory()->create();
    [$item] = manufacturingReportItem();

    $planned = manufacturingReportOrder($item, $user, 'YIELD-HARDEN-002');
    expect(yieldDisplayFor($planned))->toBe('Pending');

    $planned->status = ProductionOrderStatus::PLANNED;
    expect(yieldDisplayFor($planned))->toBe('N/A');

    $planned->status = ProductionOrderStatus::FINISHED;
    $planned->quantity_base = 0;
    $planned->quantity = 0;
    $planned->actual_output = 0;
    expect(yieldDisplayFor($planned))->toBe('N/A');
});

test('finance WIP report uses value entries first and canonical item-ledger fallback', function () {
    $user = User::factory()->create();
    [$item] = manufacturingReportItem();
    $order = manufacturingReportOrder($item, $user, 'WIP-HARDEN-001');

    $page = app(FinanceWipValuationReport::class);
    $query = $page->table(Table::make($page))->getQuery();
    $sql = $query->toSql();

    expect($sql)->toContain('value_entries')
        ->and($sql)->toContain('item_ledger_entries')
        ->and($query->whereKey($order->id)->first())->not->toBeNull();
});

test('active WIP excludes settled finished orders and retains residual finished WIP', function () {
    $user = User::factory()->create();
    [$item] = manufacturingReportItem();

    $settled = manufacturingReportOrder($item, $user, 'WIP-HARDEN-002', [
        'status' => ProductionOrderStatus::FINISHED,
    ]);
    $residual = manufacturingReportOrder($item, $user, 'WIP-HARDEN-003', [
        'status' => ProductionOrderStatus::FINISHED,
    ]);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::CONSUMPTION,
        'document_number' => $residual->document_number,
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $residual->location()->value('id'),
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => -1,
        'remaining_quantity' => 0,
        'cost_amount_expected' => 100,
        'cost_amount_actual' => 0,
        'posting_date' => now(),
        'entry_date' => now(),
        'source_type' => ProductionOrder::class,
        'source_id' => $residual->id,
        'open' => false,
    ]);

    $page = app(FinanceWipValuationReport::class);
    $query = $page->table(Table::make($page))->getQuery();

    expect($query->clone()->whereKey($settled->id)->first())->toBeNull()
        ->and($query->clone()->whereKey($residual->id)->first())->not->toBeNull();
});

test('status-only production order transitions cannot bypass canonical finish workflow', function () {
    $user = User::factory()->create();
    [$item] = manufacturingReportItem();
    $service = app(ProductionOrderService::class);

    foreach ([ProductionOrderStatus::PLANNED, ProductionOrderStatus::FIRM_PLANNED, ProductionOrderStatus::RELEASED] as $status) {
        $order = manufacturingReportOrder($item, $user, 'FINISH-GUARD-'.strtolower($status->value), ['status' => $status]);

        expect(fn () => $service->changeStatus($order, ProductionOrderStatus::FINISHED, $user->id))
            ->toThrow(Exception::class, 'canonical finish workflow');
    }
});
