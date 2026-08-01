<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionReservationType: string
{
    case ExistingInventory = 'existing_inventory';
    case ChildOutput = 'child_output';
    case ManualSupply = 'manual_supply';
}
