<?php
// app/Enums/AccountStructuralType.php — BC's "Account Type"

namespace App\Enums;

enum AccountStructuralType: string
{
    case POSTING = 'posting';           // Actual transactional accounts
    case HEADING = 'heading';           // Descriptive only
    case BEGIN_TOTAL = 'begin_total';   // Start of summation range
    case END_TOTAL = 'end_total';       // End of summation, shows total
    case TOTAL = 'total';               // Mid-level total

    public function allowsPosting(): bool
    {
        return $this === self::POSTING;
    }

    public function isTotal(): bool
    {
        return in_array($this, [self::TOTAL, self::END_TOTAL]);
    }
}
