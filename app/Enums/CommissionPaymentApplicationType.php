<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionPaymentApplicationType: string
{
    case Payment = 'payment';
    case Reversal = 'reversal';
    case Withholding = 'withholding';
    case Rounding = 'rounding';
}
