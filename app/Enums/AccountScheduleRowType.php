<?php

namespace App\Enums;

enum AccountScheduleRowType: string
{
    case NET_CHANGE = 'Net Change';
    case BALANCE_AT_DATE = 'Balance at Date';
    case BEGINNING_BALANCE = 'Beginning Balance';
}
