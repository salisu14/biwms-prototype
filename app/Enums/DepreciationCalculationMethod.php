<?php

declare(strict_types=1);

namespace App\Enums;

enum DepreciationCalculationMethod: string
{
    case STRAIGHT_LINE = 'straight_line';
    case DB1_SL = 'db1_sl';  // Declining Balance 1 with Straight Line switch
    case DB2_SL = 'db2_sl';  // Declining Balance 2 with Straight Line switch

    public function switchToStraightLine(): bool
    {
        return in_array($this, [self::DB1_SL, self::DB2_SL]);
    }
}
