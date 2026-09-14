<?php

declare(strict_types=1);

use App\Enums\SalesPriceSource;
use App\Models\Item;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesPrice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $columns
 */
function assertSalesColumnsExist(string $table, array $columns): void
{
    foreach ($columns as $column) {
        expect(Schema::hasColumn($table, $column))
            ->toBeTrue("Expected {$table}.{$column} to exist.");
    }
}

/**
 * @return array<string, mixed>
 */
function salesColumn(string $table, string $column): array
{
    $definition = collect(Schema::getColumns($table))->firstWhere('name', $column);

    expect($definition)->not->toBeNull("Expected column {$table}.{$column} to exist.");

    return $definition;
}

test('sales LCY representation columns exist and are nullable', function (): void {
    $expectations = [
        'sales_orders' => ['subtotal_lcy', 'line_discount_total_lcy', 'invoice_discount_amount_lcy', 'total_amount_lcy', 'total_vat_lcy', 'grand_total_lcy'],
        'sales_order_lines' => ['unit_price_lcy', 'line_total_lcy', 'line_amount_lcy', 'line_discount_amount_lcy', 'vat_amount_lcy'],
        'sales_invoices' => ['currency_factor', 'currency_id', 'total_amount_lcy'],
        'sales_invoice_lines' => ['unit_price_lcy', 'line_total_lcy', 'discount_amount_lcy', 'vat_amount_lcy'],
        'posted_sales_invoices' => ['subtotal_lcy', 'line_discount_total_lcy', 'invoice_discount_amount_lcy', 'total_amount_lcy', 'total_vat_lcy', 'grand_total_lcy', 'remaining_amount_lcy'],
        'posted_sales_invoice_lines' => ['unit_price_lcy', 'line_total_lcy', 'line_amount_lcy', 'line_discount_amount_lcy', 'vat_amount_lcy'],
        'sales_credit_memos' => ['currency_factor', 'total_amount_lcy'],
        'sales_credit_memo_lines' => ['unit_price_lcy', 'line_discount_amount_lcy', 'vat_amount_lcy', 'amount_lcy', 'amount_including_vat_lcy'],
        'posted_sales_credit_memos' => ['subtotal_lcy', 'total_amount_lcy', 'total_vat_lcy', 'grand_total_lcy', 'remaining_amount_lcy'],
        'posted_sales_credit_memo_lines' => ['unit_price_lcy', 'line_total_lcy', 'line_amount_lcy', 'line_discount_amount_lcy', 'vat_amount_lcy'],
        'sales_shipment_lines' => ['unit_price_lcy', 'line_amount_lcy'],
    ];

    foreach ($expectations as $table => $columns) {
        assertSalesColumnsExist($table, $columns);

        foreach ($columns as $column) {
            if (str_ends_with($column, '_lcy') || $column === 'currency_factor' || $column === 'currency_id') {
                expect(salesColumn($table, $column)['nullable'])
                    ->toBeTrue("Expected {$table}.{$column} to be nullable so historical rows stay valid.");
            }
        }
    }
});

test('unsafe sales factor defaults were removed where every creation path supplies the factor', function (): void {
    foreach (['sales_orders', 'posted_sales_invoices', 'sales_shipment_headers'] as $table) {
        $column = salesColumn($table, 'currency_factor');

        expect($column['nullable'])->toBeTrue("Expected {$table}.currency_factor to be nullable.")
            ->and($column['default'])->toBeNull("Expected {$table}.currency_factor to have no default.");
    }

    // Newly introduced rate columns must not default either.
    expect(salesColumn('sales_invoices', 'currency_factor')['default'])->toBeNull()
        ->and(salesColumn('sales_credit_memos', 'currency_factor')['default'])->toBeNull();
});

test('retained sales defaults are the documented exceptions', function (): void {
    // sales_orders.currency_code is NOT NULL but carries no unsafe USD default:
    // the dead ConvertQuoteToOrderAction that once justified it is unreferenced,
    // and every live creation path supplies a validated currency context.
    $orderCurrency = salesColumn('sales_orders', 'currency_code');
    expect($orderCurrency['nullable'])->toBeFalse()
        ->and($orderCurrency['default'])->toBeNull();

    // posted_sales_credit_memos.currency_factor keeps its default because
    // PostedSalesCreditMemo::correct() creates a memo without supplying one.
    $cmFactor = salesColumn('posted_sales_credit_memos', 'currency_factor');
    expect((string) $cmFactor['default'])->toContain('1');
});

test('new sales documents do not fabricate LCY values', function (): void {
    $order = new SalesOrder;
    $order->forceFill([
        'currency_code' => 'USD',
        'currency_factor' => '1500',
        'subtotal' => '180',
        'grand_total' => '180',
    ]);

    expect($order->subtotal_lcy)->toBeNull()
        ->and($order->grand_total_lcy)->toBeNull();

    $orderLine = new SalesOrderLine;
    $orderLine->forceFill(['quantity' => '1', 'unit_price' => '180', 'line_total' => '180', 'line_amount' => '180']);

    expect($orderLine->unit_price_lcy)->toBeNull()
        ->and($orderLine->line_total_lcy)->toBeNull()
        ->and($orderLine->line_amount_lcy)->toBeNull();

    $postedInvoice = new PostedSalesInvoice;
    $postedInvoice->forceFill(['currency_code' => 'USD', 'currency_factor' => '1500', 'grand_total' => '180']);

    expect($postedInvoice->grand_total_lcy)->toBeNull()
        ->and($postedInvoice->remaining_amount_lcy)->toBeNull();

    $postedLine = new PostedSalesInvoiceLine;
    $postedLine->forceFill(['unit_price' => '180', 'line_total' => '180', 'line_amount' => '180']);

    expect($postedLine->unit_price_lcy)->toBeNull()
        ->and($postedLine->line_amount_lcy)->toBeNull();

    $cmLine = new SalesCreditMemoLine;
    $cmLine->forceFill(['quantity' => '-1', 'unit_price' => '180', 'amount' => '-180']);

    expect($cmLine->unit_price_lcy)->toBeNull()
        ->and($cmLine->amount_lcy)->toBeNull();
});

test('LCY fields are persistable through the sales models', function (): void {
    expect((new SalesOrder)->getFillable())->toContain('grand_total_lcy', 'total_amount_lcy')
        ->and((new SalesOrderLine)->getFillable())->toContain('unit_price_lcy', 'line_total_lcy', 'line_amount_lcy')
        ->and((new PostedSalesInvoice)->getFillable())->toContain('currency_id', 'grand_total_lcy', 'remaining_amount_lcy')
        ->and((new PostedSalesInvoiceLine)->getFillable())->toContain('unit_price_lcy', 'line_amount_lcy')
        ->and((new PostedSalesCreditMemo)->getFillable())->toContain('currency_id', 'grand_total_lcy')
        ->and((new SalesCreditMemo)->getFillable())->toContain('currency_factor', 'total_amount_lcy');
});

test('sales_prices table requires an explicit currency and no default is fabricated', function (): void {
    expect(Schema::hasTable('sales_prices'))->toBeTrue();

    $currency = salesColumn('sales_prices', 'currency_code');
    expect($currency['nullable'])->toBeFalse()
        ->and($currency['default'])->toBeNull();

    expect(salesColumn('sales_prices', 'price')['nullable'])->toBeFalse()
        ->and(salesColumn('sales_prices', 'customer_id')['nullable'])->toBeTrue()
        ->and(salesColumn('sales_prices', 'customer_group_id')['nullable'])->toBeTrue()
        ->and(salesColumn('sales_prices', 'effective_from')['nullable'])->toBeTrue()
        ->and(salesColumn('sales_prices', 'effective_to')['nullable'])->toBeTrue();
});

test('a SalesPrice keeps a negotiated foreign price distinct from an LCY reference price', function (): void {
    $price = new SalesPrice;
    $price->forceFill([
        'currency_code' => 'USD',
        'price' => '220',
        'source' => SalesPriceSource::NEGOTIATED->value,
    ]);

    $item = new Item;
    $item->forceFill(['unit_price' => '300000']);

    expect($price->currency_code)->toBe('USD')
        ->and($price->price)->toBe('220.0000')
        ->and($price->source)->toBe(SalesPriceSource::NEGOTIATED)
        ->and($price->price)->not->toBe($item->unit_price)
        ->and((new SalesPrice)->getFillable())->toContain('currency_code', 'price', 'source', 'customer_group_id');
});

test('sales_prices rejects a row that omits its currency and accepts an explicit one', function (): void {
    $item = Item::factory()->create();

    $insert = fn (array $attributes): bool => DB::transaction(fn (): bool => DB::table('sales_prices')->insert($attributes + [
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect(fn () => $insert([
        'item_id' => $item->id,
        'price' => '220',
    ]))->toThrow(QueryException::class);

    expect($insert([
        'item_id' => $item->id,
        'currency_code' => 'USD',
        'price' => '220',
        'source' => SalesPriceSource::NEGOTIATED->value,
    ]))->toBeTrue();

    expect(DB::table('sales_prices')->where('item_id', $item->id)->value('currency_code'))->toBe('USD');
});

test('sales_prices currency_code is stored as a non-padded varchar', function (): void {
    expect(salesColumn('sales_prices', 'currency_code')['type_name'])->toBe('varchar');
});

test('sales_prices FK deletes preserve pricing scope', function (): void {
    // The test database retains schemas from previous runs, so the constraint
    // name alone is ambiguous. Scope the lookup to the live schema.
    $schema = DB::selectOne('select current_schema() as schema')->schema;

    $rules = [
        'sales_prices_item_id_foreign' => 'CASCADE',
        'sales_prices_customer_id_foreign' => 'CASCADE',
        'sales_prices_customer_group_id_foreign' => 'CASCADE',
        'sales_prices_created_by_foreign' => 'SET NULL',
    ];

    foreach ($rules as $constraint => $expected) {
        $rule = DB::table('information_schema.referential_constraints')
            ->where('constraint_schema', $schema)
            ->where('constraint_name', $constraint)
            ->value('delete_rule');

        expect(strtoupper((string) $rule))
            ->toBe($expected, "Expected {$constraint} in schema {$schema} to have delete rule {$expected}.");
    }
});

test('the currency_code default-removal rollback restores only the legacy default', function (): void {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_09_13_180000_remove_unsafe_sales_order_currency_code_default.php');

    $migration->down();

    $column = salesColumn('sales_orders', 'currency_code');
    expect($column['nullable'])->toBeFalse()
        ->and((string) $column['default'])->toContain('USD');

    $migration->up();

    $column = salesColumn('sales_orders', 'currency_code');
    expect($column['nullable'])->toBeFalse()
        ->and($column['default'])->toBeNull();
});

test('the factor-relaxation migration rollback is safe and keeps the columns nullable', function (): void {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_09_13_170000_relax_unsafe_sales_currency_factor_defaults.php');

    $migration->down();

    foreach (['sales_orders', 'posted_sales_invoices', 'sales_shipment_headers'] as $table) {
        $column = salesColumn($table, 'currency_factor');

        expect($column['nullable'])
            ->toBeTrue("down() must not re-impose NOT NULL on {$table}.currency_factor; NULL is a valid post-Phase-3A state.")
            ->and($column['default'])->not->toBeNull("down() should restore the legacy DEFAULT 1 on {$table}.currency_factor.");
    }

    $migration->up();

    foreach (['sales_orders', 'posted_sales_invoices', 'sales_shipment_headers'] as $table) {
        $column = salesColumn($table, 'currency_factor');

        expect($column['nullable'])->toBeTrue()
            ->and($column['default'])->toBeNull();
    }
});
