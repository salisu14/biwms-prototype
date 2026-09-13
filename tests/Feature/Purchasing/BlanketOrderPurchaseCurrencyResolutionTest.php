<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Models\BlanketOrder;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function blanketPoNumberSeries(): void
{
    $series = NumberSeries::query()->updateOrCreate(
        ['code' => 'PURCHASE'],
        [
            'description' => 'Blanket conversion purchase order series',
            'prefix' => 'PO-',
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'purchase',
        ],
    );

    $series->lines()->delete();

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => 'PO-',
        'suffix' => '',
        'blocked' => false,
    ]);
}

function makeBlanketOrder(array $attributes = []): BlanketOrder
{
    $user = User::factory()->create();
    Location::factory()->create(['code' => 'MAIN']);

    return BlanketOrder::query()->create(array_merge([
        'document_number' => 'BPO-'.uniqid(),
        'order_type' => 'Purchase',
        'status' => 'ACTIVE',
        'released' => true,
        'order_date' => '2026-09-13',
        'location_code' => 'MAIN',
        'created_by' => $user->id,
        'currency_code' => null,
        'exchange_rate' => null,
    ], $attributes));
}

test('a blanket order with an NGN vendor converts to an NGN purchase order', function (): void {
    blanketPoNumberSeries();
    $vendor = Vendor::factory()->create(['currency' => 'NGN']);
    $blanket = makeBlanketOrder(['vendor_id' => $vendor->id, 'currency_code' => null]);

    $order = $blanket->createPurchaseOrder();

    expect($order->currency_code)->toBe('NGN')
        ->and($order->currency_factor)->not->toBeNull()
        ->and((float) $order->currency_factor)->toBe(1.0)
        ->and($order->hasResolvableCurrencyFactor())->toBeTrue()
        ->and($order->status)->toBe(PurchaseOrderStatus::PENDING);
});

test('a blanket order with a USD vendor converts to a USD purchase order without fabricating a rate', function (): void {
    blanketPoNumberSeries();
    $vendor = Vendor::factory()->create(['currency' => 'USD']);
    $blanket = makeBlanketOrder(['vendor_id' => $vendor->id, 'currency_code' => null]);

    $order = $blanket->createPurchaseOrder();

    expect($order->currency_code)->toBe('USD')
        ->and($order->currency_factor)->toBeNull()
        ->and($order->hasResolvableCurrencyFactor())->toBeFalse()
        ->and($order->status)->toBe(PurchaseOrderStatus::PENDING);
});

test('a foreign blanket order inherits its explicit rate into the purchase order', function (): void {
    blanketPoNumberSeries();
    $vendor = Vendor::factory()->create(['currency' => 'USD']);
    $blanket = makeBlanketOrder([
        'vendor_id' => $vendor->id,
        'currency_code' => 'USD',
        'exchange_rate' => 1500,
    ]);

    $order = $blanket->createPurchaseOrder();

    expect($order->currency_code)->toBe('USD')
        ->and((float) $order->currency_factor)->toBe(1500.0)
        ->and($order->hasResolvableCurrencyFactor())->toBeTrue();
});

test('an explicit blanket currency overrides the vendor currency', function (): void {
    blanketPoNumberSeries();
    $vendor = Vendor::factory()->create(['currency' => 'USD']);
    $blanket = makeBlanketOrder(['vendor_id' => $vendor->id, 'currency_code' => 'NGN']);

    $order = $blanket->createPurchaseOrder();

    expect($order->currency_code)->toBe('NGN')
        ->and((float) $order->currency_factor)->toBe(1.0);
});

test('a blanket order with no currency source falls back to LCY, never a USD database default', function (): void {
    blanketPoNumberSeries();
    $vendor = Vendor::factory()->create(['currency' => '']);
    $blanket = makeBlanketOrder(['vendor_id' => $vendor->id, 'currency_code' => null]);

    $order = $blanket->createPurchaseOrder();

    expect($order->currency_code)->toBe('NGN')
        ->and($order->currency_code)->not->toBe('USD');
});
