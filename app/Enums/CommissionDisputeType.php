<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionDisputeType: string
{
    case MissingCommission = 'missing_commission';
    case IncorrectRate = 'incorrect_rate';
    case IncorrectBasis = 'incorrect_basis';
    case IncorrectReferral = 'incorrect_referral';
    case IncorrectReturnReversal = 'incorrect_return_reversal';
    case IncorrectAdjustment = 'incorrect_adjustment';
    case IncorrectHold = 'incorrect_hold';
    case IncorrectForfeiture = 'incorrect_forfeiture';
    case Other = 'other';
}
