<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Location;
use App\Models\PostedPurchaseInvoiceLine;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Models\Vendor;
use App\Support\PurchasingCurrency;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('dual-currency representation columns exist across purchasing tables', function (): void {
    $expectations = [
        'purchase_orders' => ['currency_factor', 'total_amount_lcy', 'total_vat_lcy', 'grand_total_lcy'],
        'purchase_order_lines' => ['unit_cost_lcy', 'line_total_lcy'],
        'purchase_receipts' => ['exchange_rate'],
        'purchase_receipt_lines' => ['line_amount_lcy'],
        'purchase_invoices' => ['total_amount_lcy', 'total_vat_lcy', 'grand_total_lcy', 'remaining_amount_lcy'],
        'purchase_invoice_lines' => ['line_total_lcy'],
        'posted_purchase_invoices' => ['total_amount_lcy', 'total_vat_lcy', 'grand_total_lcy', 'remaining_amount_lcy'],
        'posted_purchase_invoice_lines' => ['line_total_lcy'],
    ];

    foreach ($expectations as $table => $columns) {
        foreach ($columns as $column) {
            expect(Schema::hasColumn($table, $column))->toBeTrue("Expected {$table}.{$column} to exist.");
        }
    }
});

test('canonical conversion follows LCY = FCY x factor and FCY = LCY / factor', function (): void {
    // LCY document: factor 1 keeps document and LCY amounts identical.
    expect(PurchasingCurrency::lcyFromFcy('262866.20', 1))->toBe('262866.20')
        ->and(PurchasingCurrency::fcyFromLcy('262866.20', 1))->toBe('262866.20')
        ->and(PurchasingCurrency::factorFor('NGN', null))->toBe('1.000000')
        ->and(PurchasingCurrency::isLcyFactor(null, 'NGN'))->toBeTrue();

    // USD document at 1,500: USD 180 => NGN 270,000.
    expect(PurchasingCurrency::lcyFromFcy('180', 1500))->toBe('270000.00')
        ->and(PurchasingCurrency::fcyFromLcy('270000', 1500))->toBe('180.00')
        ->and(PurchasingCurrency::isLcyFactor(1500))->toBeFalse();

    expect(PurchasingCurrency::factorFor('USD', 1500))->toBe('1500.000000')
        ->and(fn () => PurchasingCurrency::factorFor('USD', null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PurchasingCurrency::normalizeFactor(null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PurchasingCurrency::lcyFromFcy('100', 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PurchasingCurrency::lcyFromFcy('100', -1))->toThrow(InvalidArgumentException::class);
});

test('database does not silently default historical foreign-currency purchase rates to one', function (): void {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $location = Location::factory()->create();

    $orderId = DB::table('purchase_orders')->insertGetId([
        'order_number' => 'PO-FCY-NULL-RATE',
        'order_type' => 'purchase_order',
        'status' => 'PENDING',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'order_date' => '2026-09-13',
        'location_id' => $location->id,
        'currency_code' => 'USD',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('purchase_orders')->where('id', $orderId)->value('currency_factor'))->toBeNull();

    $receiptId = DB::table('purchase_receipts')->insertGetId([
        'document_number' => 'GRN-FCY-NULL-RATE',
        'vendor_id' => $vendor->id,
        'posting_date' => '2026-09-13',
        'document_date' => '2026-09-13',
        'currency_code' => 'USD',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('purchase_receipts')->where('id', $receiptId)->value('exchange_rate'))->toBeNull();
});

test('purchase order represents FCY document totals and independent LCY totals', function (): void {
    $order = new PurchaseOrder;
    $order->forceFill([
        'currency_code' => 'USD',
        'currency_factor' => '1500',
        'total_amount' => '180',
        'total_vat' => '0',
        'grand_total' => '180',
        'total_amount_lcy' => '270000',
        'total_vat_lcy' => '0',
        'grand_total_lcy' => '270000',
    ]);

    expect($order->currency_factor)->toBe('1500.000000')
        ->and($order->grand_total)->toBe('180.0000')
        ->and($order->grand_total_lcy)->toBe('270000.0000')
        ->and(PurchasingCurrency::lcyFromFcy($order->grand_total, $order->currency_factor))->toBe('270000.00');

    $line = new PurchaseOrderLine;
    $line->forceFill([
        'quantity' => '1',
        'unit_cost' => '180',
        'unit_cost_lcy' => '270000',
        'line_total' => '180',
        'line_total_lcy' => '270000',
    ]);

    expect($line->unit_cost)->toBe('180.0000')
        ->and($line->line_total)->toBe('180.0000')
        ->and($line->unit_cost_lcy)->toBe('270000.0000')
        ->and($line->line_total_lcy)->toBe('270000.0000');

    expect((new PurchaseOrderLine)->getFillable())->toContain('unit_cost_lcy', 'line_total_lcy');
});

test('foreign-currency invoice and posted lines carry FCY and LCY totals independently', function (): void {
    $line = new PurchaseInvoiceLine;
    $line->forceFill([
        'quantity' => '1',
        'unit_cost' => '180',
        'unit_cost_lcy' => '270000',
        'line_total' => '180',
        'line_total_lcy' => '270000',
    ]);

    expect($line->line_total)->toBe('180.0000')
        ->and($line->line_total_lcy)->toBe('270000.0000');

    $posted = new PostedPurchaseInvoiceLine;
    $posted->forceFill(['line_total' => '180', 'line_total_lcy' => '270000']);

    expect($posted->line_total_lcy)->toBe('270000.0000');
});

test('a new line does not silently derive LCY values', function (): void {
    $line = new PurchaseOrderLine;
    $line->forceFill(['quantity' => '1', 'unit_cost' => '180', 'line_total' => '180']);

    expect($line->unit_cost_lcy)->toBeNull()
        ->and($line->line_total_lcy)->toBeNull();
});

test('purchase order currency_code has no database default and stays NOT NULL', function (): void {
    $column = collect(Schema::getColumns('purchase_orders'))->firstWhere('name', 'currency_code');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeFalse()
        ->and($column['default'])->toBeNull();
});

test('a raw purchase order insert that omits currency_code fails instead of becoming USD', function (): void {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $location = Location::factory()->create();

    // Wrap in a savepoint so the failed insert does not abort the surrounding
    // RefreshDatabase transaction, letting us still verify no row was written.
    $insert = fn (): bool => DB::transaction(fn (): bool => DB::table('purchase_orders')->insert([
        'order_number' => 'PO-NO-CURRENCY',
        'order_type' => 'purchase_order',
        'status' => 'PENDING',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'order_date' => '2026-09-13',
        'location_id' => $location->id,
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect($insert)->toThrow(QueryException::class)
        ->and(DB::table('purchase_orders')->where('order_number', 'PO-NO-CURRENCY')->exists())->toBeFalse();
});

test('item reference cost is independent from the negotiated document price', function (): void {
    $item = new Item;
    $item->forceFill(['standard_cost' => '262866.20']);

    $line = new PurchaseOrderLine;
    $line->forceFill([
        'quantity' => '1',
        'unit_cost' => '180',
        'unit_cost_lcy' => '270000',
        'line_total' => '180',
        'line_total_lcy' => '270000',
    ]);

    expect($item->standard_cost)->toBe('262866.20000000')
        ->and($line->unit_cost)->toBe('180.0000')
        ->and($line->unit_cost)->not->toBe($item->standard_cost)
        ->and($line->line_total_lcy)->toBe('270000.0000');
});
