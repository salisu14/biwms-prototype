<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionSupplyLinkStatus: string
{
    case Planned = 'planned';
    case ChildOrderCreated = 'child_order_created';
    case PartiallyProduced = 'partially_produced';
    case Available = 'available';
    case PartiallySupplied = 'partially_supplied';
    case Supplied = 'supplied';
    case Cancelled = 'cancelled';
    case Exception = 'exception';
}
