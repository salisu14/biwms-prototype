<?php

namespace App\Enums;

/**
 * Optional: Enum for 'debit_credit' column.
 */
enum DebitCreditType: string
{
    case BOTH = 'Both';
    case DEBIT = 'Debit';
    case CREDIT = 'Credit';
}
