<?php

// app/Enums/LocationType.php

namespace App\Enums;

enum LocationType: string
{
    case RECEIVING = 'RECEIVING';
    case QUARANTINE = 'QUARANTINE';
    case APPROVED = 'APPROVED';
    case PRODUCTION = 'PRODUCTION';
    case SHIPPING = 'SHIPPING';
    case RETURNS = 'RETURNS';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVING => 'Receiving Bay',
            self::QUARANTINE => 'Quarantine/Hold',
            self::APPROVED => 'Approved Storage',
            self::PRODUCTION => 'Production Floor',
            self::SHIPPING => 'Shipping/Staging',
            self::RETURNS => 'Returns Processing',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RECEIVING => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            self::QUARANTINE => 'bg-red-100 text-red-800 border-red-300',
            self::APPROVED => 'bg-green-100 text-green-800 border-green-300',
            self::PRODUCTION => 'bg-blue-100 text-blue-800 border-blue-300',
            self::SHIPPING => 'bg-purple-100 text-purple-800 border-purple-300',
            self::RETURNS => 'bg-orange-100 text-orange-800 border-orange-300',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RECEIVING => 'truck-loading',
            self::QUARANTINE => 'ban',
            self::APPROVED => 'check-double',
            self::PRODUCTION => 'cogs',
            self::SHIPPING => 'shipping-fast',
            self::RETURNS => 'undo',
        };
    }

    /**
     * Whether inventory here is available for use
     */
    public function isAvailable(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Whether inventory requires QA release
     */
    public function requiresQaRelease(): bool
    {
        return in_array($this, [self::RECEIVING, self::QUARANTINE, self::RETURNS]);
    }

    /**
     * Whether this is a temporary location
     */
    public function isTemporary(): bool
    {
        return in_array($this, [self::RECEIVING, self::SHIPPING, self::QUARANTINE]);
    }

    /**
     * Get allowed transfer destinations
     */
    public function allowedDestinations(): array
    {
        return match ($this) {
            self::RECEIVING => [self::QUARANTINE],
            self::QUARANTINE => [self::APPROVED, self::RETURNS],
            self::APPROVED => [self::PRODUCTION, self::SHIPPING, self::RETURNS],
            self::PRODUCTION => [self::APPROVED, self::SHIPPING],
            self::SHIPPING => [], // Terminal
            self::RETURNS => [self::QUARANTINE, self::APPROVED],
        };
    }

    /**
     * GMP compliance level
     */
    public function gmpLevel(): string
    {
        return match ($this) {
            self::PRODUCTION => 'Grade A/B',
            self::APPROVED, self::QUARANTINE => 'Grade C',
            default => 'Grade D',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
                'available' => $case->isAvailable(),
                'requires_qa' => $case->requiresQaRelease(),
            ])
            ->toArray();
    }
}
