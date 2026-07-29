<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionQualityResult: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case Conditional = 'conditional';
    case Waived = 'waived';
}
