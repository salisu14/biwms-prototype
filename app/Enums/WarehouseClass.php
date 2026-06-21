<?php

declare(strict_types=1);

namespace App\Enums;

enum WarehouseClass: string
{
    case STANDARD = 'standard';
    case REFRIGERATED = 'refrigerated';
    case FROZEN = 'frozen';
    case HAZARDOUS = 'hazardous';
    case HIGH_VALUE = 'high_value';
    case QUARANTINE = 'quarantine';
}
