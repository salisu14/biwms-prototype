<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionLedgerEntryStatus: string
{
    case Open = 'open';
    case ApprovedForFuturePayment = 'approved_for_future_payment';
    case Reversed = 'reversed';
    case Forfeited = 'forfeited';
}
