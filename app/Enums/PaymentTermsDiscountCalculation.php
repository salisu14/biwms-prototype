<?php

namespace App\Enums;

enum PaymentTermsDiscountCalculation: string
{
    case NET_DAYS = 'net_days';
    case END_OF_MONTH = 'end_of_month';
    case DUE_DATE = 'due_date';

    public function label(): string
    {
        return match ($this) {
            self::NET_DAYS => 'Net Days',
            self::END_OF_MONTH => 'End of Month',
            self::DUE_DATE => 'Due Date',
        };
    }
}
