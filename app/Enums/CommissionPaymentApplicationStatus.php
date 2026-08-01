<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionPaymentApplicationStatus: string
{
    case Applied = 'applied';
    case Reversed = 'reversed';
}
