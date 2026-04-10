<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckType: string
{
    case COMPUTER_CHECK = 'computer_check';
    case MANUAL_CHECK = 'manual_check';
    case ELECTRONIC_PAYMENT = 'electronic_payment';

    public function label(): string
    {
        return match ($this) {
            self::COMPUTER_CHECK => 'Computer Check',
            self::MANUAL_CHECK => 'Manual Check',
            self::ELECTRONIC_PAYMENT => 'Electronic Payment',
        };
    }

    /**
     * Check if this is a printed check (requires check stock)
     */
    public function isPrinted(): bool
    {
        return $this === self::COMPUTER_CHECK || $this === self::MANUAL_CHECK;
    }

    /**
     * Check if this is an electronic payment (ACH, wire, etc.)
     */
    public function isElectronic(): bool
    {
        return $this === self::ELECTRONIC_PAYMENT;
    }

    /**
     * Check if check number is required
     */
    public function requiresCheckNo(): bool
    {
        return $this === self::COMPUTER_CHECK || $this === self::MANUAL_CHECK;
    }

    /**
     * Get default check layout for printing
     */
    public function defaultLayout(): ?string
    {
        return match ($this) {
            self::COMPUTER_CHECK => 'standard_check',
            self::MANUAL_CHECK => null, // Handwritten, no layout
            self::ELECTRONIC_PAYMENT => 'remittance_advice',
        };
    }
}
