<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Provenance of a sales price.
 *
 * The source distinguishes how a price came to exist so that a negotiated
 * foreign-currency price is never conflated with an LCY reference/list price.
 */
enum SalesPriceSource: string implements HasColor, HasLabel
{
    case REFERENCE = 'REFERENCE';
    case NEGOTIATED = 'NEGOTIATED';
    case CONTRACT = 'CONTRACT';
    case PROMOTION = 'PROMOTION';
    case MANUAL = 'MANUAL';

    public function getLabel(): string
    {
        return match ($this) {
            self::REFERENCE => 'Reference',
            self::NEGOTIATED => 'Negotiated',
            self::CONTRACT => 'Contract',
            self::PROMOTION => 'Promotion',
            self::MANUAL => 'Manual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::REFERENCE => 'gray',
            self::NEGOTIATED => 'success',
            self::CONTRACT => 'info',
            self::PROMOTION => 'warning',
            self::MANUAL => 'primary',
        };
    }
}
