<?php

declare(strict_types=1);

namespace App\Enums;

enum CurrencyRoundingMethod: string
{
    case NEAREST = 'nearest';
    case UP = 'up';
    case DOWN = 'down';

    public function label(): string
    {
        return match ($this) {
            self::NEAREST => 'Nearest',
            self::UP => 'Up (Away from Zero)',
            self::DOWN => 'Down (Toward Zero)',
        };
    }

    public function round(float $amount, float $precision): float
    {
        return match ($this) {
            self::NEAREST => round($amount / $precision) * $precision,
            self::UP => ceil($amount / $precision) * $precision,
            self::DOWN => floor($amount / $precision) * $precision,
        };
    }
}
