<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionLedgerEntryType: string
{
    case Accrual = 'accrual';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';
    case Forfeiture = 'forfeiture';
    case LiabilityRecognition = 'liability_recognition';
    case LiabilityReversal = 'liability_reversal';
    case Payment = 'payment';
    case PaymentReversal = 'payment_reversal';
    case PaymentApplicationReservedForFuturePhase = 'payment_application_reserved_for_future_phase';
}
