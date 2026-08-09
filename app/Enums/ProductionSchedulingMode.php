<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionSchedulingMode: string
{
    case Forward = 'forward';
    case Backward = 'backward';
}
