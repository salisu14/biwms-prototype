<?php

declare(strict_types=1);

use App\Support\DocumentCurrency;

/**
 * Phase 3A — shared DocumentCurrency contract.
 *
 * These assertions compare exact decimal strings (never floats), matching the
 * codebase decimal conventions.
 */
test('DocumentCurrency resolves LCY factor one and requires explicit foreign factors', function (): void {
    expect(DocumentCurrency::factorFor('NGN', null))->toBe('1.000000')
        ->and(DocumentCurrency::factorFor('ngn', null))->toBe('1.000000')
        ->and(DocumentCurrency::factorFor('USD', 1500))->toBe('1500.000000')
        ->and(DocumentCurrency::normalizeFactor(1500))->toBe('1500.000000')
        ->and(DocumentCurrency::normalizeFactor('1500'))->toBe('1500.000000')
        ->and(DocumentCurrency::isLcyFactor(null, 'NGN'))->toBeTrue()
        ->and(DocumentCurrency::isLcyFactor(1, 'USD'))->toBeTrue()
        ->and(DocumentCurrency::isLcyFactor(1500, 'USD'))->toBeFalse();
});

test('DocumentCurrency rejects a missing foreign-currency factor', function (): void {
    expect(fn () => DocumentCurrency::factorFor('USD', null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::normalizeFactor(null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::normalizeFactor(''))->toThrow(InvalidArgumentException::class);
});

test('DocumentCurrency rejects zero and negative factors', function (): void {
    expect(fn () => DocumentCurrency::normalizeFactor(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::normalizeFactor('0'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::normalizeFactor(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::factorFor('USD', 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::factorFor('USD', -1))->toThrow(InvalidArgumentException::class);
});

test('DocumentCurrency converts foreign amounts and keeps LCY identity', function (): void {
    // USD 220 at 1,500 => NGN 330,000 and back.
    expect(DocumentCurrency::toLcy('220', 'USD', 1500))->toBe('330000.00')
        ->and(DocumentCurrency::fromLcy('330000', 'USD', 1500))->toBe('220.00')
        ->and(DocumentCurrency::lcyFromFcy('180', 1500))->toBe('270000.00')
        ->and(DocumentCurrency::fcyFromLcy('270000', 1500))->toBe('180.00');

    // LCY documents are an identity conversion regardless of the null factor.
    expect(DocumentCurrency::toLcy('262866.20', 'NGN', null))->toBe('262866.20')
        ->and(DocumentCurrency::fromLcy('262866.20', 'NGN', null))->toBe('262866.20')
        ->and(DocumentCurrency::toLcy('262866.20', 'NGN', 1))->toBe('262866.20');

    // A foreign document stamped factor 1 is not converted (still FCY value).
    expect(DocumentCurrency::toLcy('262866.20', 'USD', 1))->toBe('262866.20');
});

test('DocumentCurrency propagates missing/invalid factors through conversions', function (): void {
    expect(fn () => DocumentCurrency::toLcy('100', 'USD', null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::fromLcy('100', 'USD', null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::toLcy('100', 'USD', 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::fromLcy('100', 'USD', -1))->toThrow(InvalidArgumentException::class);
});

test('DocumentCurrency normalizes currency codes and predicates safely', function (): void {
    expect(DocumentCurrency::normalizeCurrencyCode(' usd '))->toBe('USD')
        ->and(DocumentCurrency::normalizeCurrencyCode('ngn'))->toBe('NGN')
        ->and(DocumentCurrency::isLocalCurrency('ngn'))->toBeTrue()
        ->and(DocumentCurrency::isLocalCurrency('USD'))->toBeFalse()
        ->and(DocumentCurrency::isLocalCurrency(null))->toBeFalse()
        ->and(DocumentCurrency::isLocalCurrency(''))->toBeFalse();

    expect(fn () => DocumentCurrency::normalizeCurrencyCode(null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::normalizeCurrencyCode(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::normalizeCurrencyCode('US'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::normalizeCurrencyCode('USDD'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::normalizeCurrencyCode('12'))->toThrow(InvalidArgumentException::class);
});

test('DocumentCurrency fails closed on a missing currency code instead of inferring LCY', function (): void {
    expect(fn () => DocumentCurrency::factorFor(null, null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::factorFor('', null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::factorFor(null, 1500))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::isLcyFactor(null, ''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::toLcy('100', null, 1500))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::fromLcy('100', null, 1500))->toThrow(InvalidArgumentException::class)
        ->and(DocumentCurrency::isLocalCurrency(null))->toBeFalse();
});

test('DocumentCurrency handles zero amounts without fabrication', function (): void {
    expect(DocumentCurrency::toLcy('0', 'USD', 1500))->toBe('0.00')
        ->and(DocumentCurrency::fromLcy('0', 'USD', 1500))->toBe('0.00')
        ->and(DocumentCurrency::toLcy('0', 'NGN', null))->toBe('0.00')
        ->and(DocumentCurrency::fromLcy('0', 'NGN', null))->toBe('0.00')
        ->and(DocumentCurrency::lcyFromFcy('0', 1500))->toBe('0.00');
});

test('DocumentCurrency converts negative amounts symmetrically', function (): void {
    expect(DocumentCurrency::toLcy('-220', 'USD', 1500))->toBe('-330000.00')
        ->and(DocumentCurrency::fromLcy('-330000', 'USD', 1500))->toBe('-220.00')
        ->and(DocumentCurrency::toLcy('-262866.20', 'NGN', null))->toBe('-262866.20')
        ->and(DocumentCurrency::lcyFromFcy('-180', 1500))->toBe('-270000.00')
        ->and(DocumentCurrency::fcyFromLcy('-270000', 1500))->toBe('-180.00');
});

test('DocumentCurrency supports fractional factors deterministically', function (): void {
    expect(DocumentCurrency::factorFor('USD', '0.85'))->toBe('0.850000')
        ->and(DocumentCurrency::toLcy('100', 'USD', '0.85'))->toBe('85.00')
        ->and(DocumentCurrency::normalizeFactor('12.345678'))->toBe('12.345678')
        ->and(DocumentCurrency::lcyFromFcy('100', '12.345678'))->toBe('1234.57');
});

test('DocumentCurrency accepts very small positive factors but rejects those that round to zero', function (): void {
    expect(DocumentCurrency::normalizeFactor('0.000001'))->toBe('0.000001')
        ->and(DocumentCurrency::normalizeFactor('0.0001'))->toBe('0.000100');

    // Below the factor scale the value rounds to zero and must fail closed.
    expect(fn () => DocumentCurrency::normalizeFactor('0.0000001'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DocumentCurrency::normalizeFactor('0.0000004'))->toThrow(InvalidArgumentException::class);
});
