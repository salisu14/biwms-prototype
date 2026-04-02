<?php

namespace App\Enums;

/**
 * Corresponds to the 'account_type' column in gl_accounts.
 * Common in ERP systems like Microsoft Dynamics 365 Business Central.
 */
enum GlAccountType: string
{
    case POSTING = 'Posting';
    case HEADING = 'Heading';
    case TOTAL = 'Total';
    case BEGIN_TOTAL = 'Begin-Total';
    case END_TOTAL = 'End-Total';

    case CAPEX = 'Capex';

    public function label(): string
    {
        return match ($this) {
            self::POSTING => 'Posting',
            self::HEADING => 'Heading',
            self::TOTAL => 'Total',
            self::BEGIN_TOTAL => 'Begin-Total',
            self::END_TOTAL => 'End-Total',
            self::CAPEX => 'Capex',
        };
    }
}
