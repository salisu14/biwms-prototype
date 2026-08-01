<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionSupplyType: string
{
    case ExistingInventory = 'existing_inventory';
    case GeneratedChildOrder = 'generated_child_order';
    case ManualChildOrder = 'manual_child_order';
    case ManualSupply = 'manual_supply';
}
