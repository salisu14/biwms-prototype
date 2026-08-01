<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionHierarchyNodeType: string
{
    case RootOutput = 'root_output';
    case ManufacturedComponent = 'manufactured_component';
    case PurchasedComponent = 'purchased_component';
    case PackagingComponent = 'packaging_component';
    case ConsumableComponent = 'consumable_component';
    case ServiceComponent = 'service_component';
    case Exception = 'exception';
}
