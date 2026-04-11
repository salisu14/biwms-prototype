<?php

namespace App\Enums;

/**
 * Defines the restriction level between two different Dimension types.
 */
enum DimensionCombinationRestriction: string
{
    case NoLimitation = 'no_limitation';
    case Limited = 'limited';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::NoLimitation => 'No Limitation',
            self::Limited => 'Limited (Check Specific Values)',
            self::Blocked => 'Blocked (Cannot Combine)',
        };
    }

    public function isRestricted(): bool
    {
        return $this !== self::NoLimitation;
    }
}
