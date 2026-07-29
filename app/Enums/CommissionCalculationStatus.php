<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionCalculationStatus: string
{
    case Pending = 'pending';
    case Calculated = 'calculated';
    case PartiallyEligible = 'partially_eligible';
    case Ineligible = 'ineligible';
    case Accrued = 'accrued';
    case Reversed = 'reversed';
    case Superseded = 'superseded';
    case Failed = 'failed';
}
