<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionQualityInspectionStage: string
{
    case BeforeOperation = 'before_operation';
    case DuringOperation = 'during_operation';
    case AfterOperation = 'after_operation';
    case BeforeOutput = 'before_output';
    case AfterOutput = 'after_output';
}
