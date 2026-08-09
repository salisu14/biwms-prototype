<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionIntermediateHandoffStatus: string
{
    case Planned = 'planned';
    case WaitingOutput = 'waiting_output';
    case PartiallyAvailable = 'partially_available';
    case Available = 'available';
    case PartiallyConsumed = 'partially_consumed';
    case Consumed = 'consumed';
    case QualityBlocked = 'quality_blocked';
    case Cancelled = 'cancelled';
    case Invalid = 'invalid';
}
