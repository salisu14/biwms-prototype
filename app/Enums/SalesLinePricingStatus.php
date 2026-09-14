<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Durable pricing state of a Sales document line.
 *
 * An ERP must distinguish "no price could be resolved" from "the price is
 * genuinely zero". Unresolved foreign-currency pricing is a workflow blocker,
 * not a free-of-charge sale, and must never silently reach approval, shipment
 * or invoicing as zero.
 *
 * - RESOLVED: priced from a trusted, explicit-currency eligible source.
 * - MANUAL: a user deliberately entered the document-currency price.
 * - UNRESOLVED: no trusted eligible price exists; user action is required.
 *
 * A legacy/NULL value means "unknown/historical" and is never inferred to be
 * one of the states above without evidence.
 */
enum SalesLinePricingStatus: string implements HasColor, HasLabel
{
    case RESOLVED = 'RESOLVED';
    case MANUAL = 'MANUAL';
    case UNRESOLVED = 'UNRESOLVED';

    public function getLabel(): string
    {
        return match ($this) {
            self::RESOLVED => 'Resolved',
            self::MANUAL => 'Manual',
            self::UNRESOLVED => 'Unresolved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::RESOLVED => 'success',
            self::MANUAL => 'info',
            self::UNRESOLVED => 'danger',
        };
    }
}
