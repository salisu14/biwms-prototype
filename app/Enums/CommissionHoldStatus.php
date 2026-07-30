<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionHoldStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
