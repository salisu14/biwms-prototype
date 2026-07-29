<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionLedgerEntryType: string
{
    case Accrual = 'accrual';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';
    case Forfeiture = 'forfeiture';
    case PaymentApplicationReservedForFuturePhase = 'payment_application_reserved_for_future_phase';
}
