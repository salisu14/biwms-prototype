<?php

declare(strict_types=1);

use App\Support\DocumentCurrency;
use App\Support\PurchasingCurrency;

/**
 * Phase 3A — characterization: the shared DocumentCurrency helper agrees with
 * the certified PurchasingCurrency wrapper on every supported public operation.
 *
 * If this test fails, the two helpers have diverged. Phase 3A must not "fix"
 * PurchasingCurrency automatically; a divergence is a reported mismatch.
 */
test('DocumentCurrency and PurchasingCurrency agree on factor resolution', function (): void {
    expect(DocumentCurrency::factorFor('NGN', null))->toBe(PurchasingCurrency::factorFor('NGN', null))
        ->and(DocumentCurrency::factorFor('USD', 1500))->toBe(PurchasingCurrency::factorFor('USD', 1500))
        ->and(DocumentCurrency::normalizeFactor(1500))->toBe(PurchasingCurrency::normalizeFactor(1500))
        ->and(DocumentCurrency::normalizeFactor('1500'))->toBe(PurchasingCurrency::normalizeFactor('1500'))
        ->and(DocumentCurrency::isLcyFactor(null, 'NGN'))->toBe(PurchasingCurrency::isLcyFactor(null, 'NGN'))
        ->and(DocumentCurrency::isLcyFactor(1500))->toBe(PurchasingCurrency::isLcyFactor(1500));
});

test('DocumentCurrency and PurchasingCurrency agree on conversion in both directions', function (): void {
    expect(DocumentCurrency::lcyFromFcy('180', 1500))->toBe(PurchasingCurrency::lcyFromFcy('180', 1500))
        ->and(DocumentCurrency::fcyFromLcy('270000', 1500))->toBe(PurchasingCurrency::fcyFromLcy('270000', 1500))
        ->and(DocumentCurrency::lcyFromFcy('262866.20', 1))->toBe(PurchasingCurrency::lcyFromFcy('262866.20', 1))
        ->and(DocumentCurrency::fcyFromLcy('262866.20', 1))->toBe(PurchasingCurrency::fcyFromLcy('262866.20', 1))
        ->and(DocumentCurrency::lcyFromFcy('1234.5678', 12.5))->toBe(PurchasingCurrency::lcyFromFcy('1234.5678', 12.5));
});

test('DocumentCurrency and PurchasingCurrency agree on rejection behavior', function (): void {
    foreach ([null, '', 0, '0', -1, '-0.5'] as $invalidFactor) {
        expect(fn () => DocumentCurrency::normalizeFactor($invalidFactor))->toThrow(InvalidArgumentException::class)
            ->and(fn () => PurchasingCurrency::normalizeFactor($invalidFactor))->toThrow(InvalidArgumentException::class);
    }

    expect(fn () => DocumentCurrency::factorFor('USD', null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PurchasingCurrency::factorFor('USD', null))->toThrow(InvalidArgumentException::class);

    expect(fn () => DocumentCurrency::lcyFromFcy('100', 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PurchasingCurrency::lcyFromFcy('100', 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::lcyFromFcy('100', -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PurchasingCurrency::lcyFromFcy('100', -1))->toThrow(InvalidArgumentException::class);
});

test('DocumentCurrency is intentionally stricter than PurchasingCurrency for a missing currency code', function (): void {
    // PurchasingCurrency legacy-infers LCY when the currency code is missing.
    expect(PurchasingCurrency::factorFor(null, null))->toBe('1.000000')
        ->and(PurchasingCurrency::factorFor(null, 1500))->toBe('1500.000000')
        ->and(PurchasingCurrency::isLcyFactor(null))->toBeTrue();

    // DocumentCurrency fails closed instead of guessing that an unresolved
    // currency is local. This is the one intentional divergence.
    expect(fn () => DocumentCurrency::factorFor(null, null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::factorFor(null, 1500))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::isLcyFactor(null))->toThrow(InvalidArgumentException::class)
        ->and(DocumentCurrency::isLocalCurrency(null))->toBeFalse();
});
