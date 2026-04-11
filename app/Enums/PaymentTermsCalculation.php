<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentTermsCalculation: string
{
    case NET = 'net'; // Net days
    case DUE_DATE = 'due_date'; // Fixed day of month
    case DUE_DAY = 'due_day'; // Specific day of next month
    case END_OF_MONTH = 'end_of_month'; // EOM + days
    case END_OF_NEXT_MONTH = 'end_of_next_month'; // EOM of next month
    case CASH_RECEIPT = 'cash_receipt'; // Cash before delivery

    public function label(): string
    {
        return match ($this) {
            self::NET => 'Net Days',
            self::DUE_DATE => 'Fixed Due Date',
            self::DUE_DAY => 'Due on Specific Day',
            self::END_OF_MONTH => 'End of Month',
            self::END_OF_NEXT_MONTH => 'End of Next Month',
            self::CASH_RECEIPT => 'Cash Receipt Required',
        };
    }
}
