<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionHierarchyReadinessClassification: string
{
    case Ready = 'ready';
    case Warning = 'warning';
    case Blocked = 'blocked';
    case Critical = 'critical';
}
