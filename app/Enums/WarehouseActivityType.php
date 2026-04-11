<?php

declare(strict_types=1);

namespace App\Enums;

enum WarehouseActivityType: string
{
    case PUT_AWAY = 'put_away';
    case PICK = 'pick';
    case MOVEMENT = 'movement';
    case INVENTORY = 'inventory';
    case RECEIPT = 'receipt';
    case SHIPMENT = 'shipment';
    case INTERNAL_PICK = 'internal_pick';
    case INTERNAL_PUT_AWAY = 'internal_put_away';

    public function label(): string
    {
        return match ($this) {
            self::PUT_AWAY => 'Put-away',
            self::PICK => 'Pick',
            self::MOVEMENT => 'Movement',
            self::INVENTORY => 'Inventory',
            self::RECEIPT => 'Receipt',
            self::SHIPMENT => 'Shipment',
            self::INTERNAL_PICK => 'Internal Pick',
            self::INTERNAL_PUT_AWAY => 'Internal Put-away',
        };
    }
}
