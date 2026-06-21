<?php

use App\Models\InventoryAdjustmentLine;
use App\Models\PhysicalInventoryLine;

it('calculates inventory adjustment discount math correctly', function () {
    $result = InventoryAdjustmentLine::calculateAmounts(
        quantity: 5,
        qtyPerUnitOfMeasure: 2,
        unitCost: 100,
        lineDiscountAmount: 50,
    );

    expect($result['quantity_base'])->toBe(10.0)
        ->and($result['line_amount'])->toBe(500.0)
        ->and($result['amount'])->toBe(450.0)
        ->and($result['quantity_to_handle'])->toBe(5.0)
        ->and($result['quantity_to_invoice'])->toBe(5.0);
});

it('calculates physical inventory zero difference correctly', function () {
    $result = PhysicalInventoryLine::calculateCountVariance(
        systemQuantity: 100,
        physicalQuantity: 100,
        unitAmount: 25,
    );

    expect($result['qty_calculated'])->toBe(0.0)
        ->and($result['entry_type'])->toBeNull()
        ->and($result['qty_to_handle'])->toBe(0.0)
        ->and($result['qty_to_invoice'])->toBe(0.0)
        ->and($result['amount'])->toBe(0.0);
});

it('calculates physical inventory negative and positive differences', function () {
    $negative = PhysicalInventoryLine::calculateCountVariance(
        systemQuantity: 100,
        physicalQuantity: 92,
        unitAmount: 7.5,
    );

    expect($negative['qty_calculated'])->toBe(-8.0)
        ->and($negative['entry_type'])->toBe('Negative Adjmt.')
        ->and($negative['qty_to_handle'])->toBe(8.0)
        ->and($negative['amount'])->toBe(60.0);

    $positive = PhysicalInventoryLine::calculateCountVariance(
        systemQuantity: 100,
        physicalQuantity: 108,
        unitAmount: 7.5,
    );

    expect($positive['qty_calculated'])->toBe(8.0)
        ->and($positive['entry_type'])->toBe('Positive Adjmt.')
        ->and($positive['qty_to_handle'])->toBe(8.0)
        ->and($positive['amount'])->toBe(60.0);
});
