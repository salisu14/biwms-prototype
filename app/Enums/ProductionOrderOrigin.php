<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionOrderOrigin: string
{
    case Standalone = 'standalone';
    case GeneratedChild = 'generated_child';
    case ManualLinkedChild = 'manual_linked_child';
}
