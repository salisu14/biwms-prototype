<?php

use App\Data\Sales\SalesInvoiceData;
use App\Enums\SalesOrderType;
use App\Exceptions\DocumentStateException;
use App\Models\Customer;
use App\Models\Item;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks sales invoice creation from a fully invoiced sales order without consuming a number', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    salesInvoiceBusinessExceptionNumberSeries();

    $customer = Customer::factory()->create();
    $item = Item::factory()->create();

    $salesOrder = SalesOrder::query()->create([
        'order_number' => 'SO-FULL-001',
        'order_type' => SalesOrderType::SalesOrder->value,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'order_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'status' => 'INVOICED',
        'quantity_shipped' => 1,
        'quantity_invoiced' => 1,
        'fully_shipped' => true,
        'fully_invoiced' => true,
        'created_by' => $user->id,
    ]);

    SalesOrderLine::query()->create([
        'sales_order_id' => $salesOrder->id,
        'line_number' => 10,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'quantity_shipped' => 1,
        'quantity_invoiced' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_price' => 100,
        'line_discount_percent' => 0,
        'line_discount_amount' => 0,
        'line_total' => 100,
        'line_amount' => 100,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'amount_including_vat' => 100,
    ]);

    expect(fn () => app(SalesInvoiceService::class)->create(new SalesInvoiceData(
        customer_id: $customer->id,
        sales_order_id: $salesOrder->id,
        invoice_date: now()->toDateString(),
        due_date: now()->addDays(30)->toDateString(),
        currency_code: 'NGN',
        lines: [],
    )))->toThrow(
        DocumentStateException::class,
        'This Sales Order has already been fully invoiced. No remaining quantity is available to invoice.',
    );

    expect(SalesInvoice::query()->count())->toBe(0)
        ->and(NumberSeriesLine::query()->whereHas('series', fn ($query) => $query->where('code', 'S-INV'))->value('last_no_used'))->toBe(0);
});

function salesInvoiceBusinessExceptionNumberSeries(): void
{
    $series = NumberSeries::query()->create([
        'code' => 'S-INV',
        'description' => 'Sales Invoice test series',
        'prefix' => 'SINV-',
        'starting_number' => 1,
        'current_number' => 0,
        'year' => 2026,
        'is_active' => true,
        'allow_manual' => false,
        'module' => 'sales',
    ]);

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => 'SINV-',
        'suffix' => '',
        'blocked' => false,
    ]);
}
