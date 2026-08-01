<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionHierarchyNodeStatus: string
{
    case Planned = 'planned';
    case Current = 'current';
    case Superseded = 'superseded';
    case Released = 'released';
    case Cancelled = 'cancelled';
    case Exception = 'exception';
}
