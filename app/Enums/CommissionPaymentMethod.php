<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionPaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case MobileMoney = 'mobile_money';
    case Other = 'other';
}
