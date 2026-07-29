<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionQualityDisposition: string
{
    case Accept = 'accept';
    case Hold = 'hold';
    case Rework = 'rework';
    case Scrap = 'scrap';
    case Reject = 'reject';
}
