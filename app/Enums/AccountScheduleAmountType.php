<?php

namespace App\Enums;

enum AccountScheduleAmountType: string
{
    case NET_AMOUNT = 'Net Amount';
    case DEBIT_AMOUNT = 'Debit Amount';
    case CREDIT_AMOUNT = 'Credit Amount';
}
