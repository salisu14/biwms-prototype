<?php

namespace App\Enums;

enum AccountScheduleTotalingType: string
{
    case POSTING_ACCOUNTS = 'Posting Accounts';
    case TOTAL_ACCOUNTS = 'Total Accounts';
    case FORMULA = 'Formula';
    case UNDERLINE = 'Underline';
    case COMMENT = 'Comment';
}
