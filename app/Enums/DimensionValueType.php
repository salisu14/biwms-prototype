<?php

namespace App\Enums;

/**
 * Defines the role of a Dimension Value in a hierarchical structure.
 * Business Central uses these to create "Begin-Total" and "End-Total"
 * brackets for reporting on groups (e.g., "All Factories").
 */
enum DimensionValueType: string
{
    case Standard = 'standard';
    case Heading = 'heading';
    case Total = 'total';
    case BeginTotal = 'begin_total';
    case EndTotal = 'end_total';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Heading => 'Heading',
            self::Total => 'Total',
            self::BeginTotal => 'Begin-Total',
            self::EndTotal => 'End-Total',
        };
    }

    /**
     * Values that can actually be posted to a ledger.
     * Totals and Headings are for structural visualization only.
     */
    public function isPostable(): bool
    {
        return $this === self::Standard;
    }
}
