<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionBomLineBasis: string
{
    case PerUnit = 'PER_UNIT';
    case PerReferenceQuantity = 'PER_REFERENCE_QUANTITY';
    case FixedPerBatch = 'FIXED_PER_BATCH';
    case ManualActual = 'MANUAL_ACTUAL';
}
