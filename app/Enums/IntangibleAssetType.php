<?php

declare(strict_types=1);

namespace App\Enums;

enum IntangibleAssetType: string
{
    case PATENT = 'patent';
    case TRADEMARK = 'trademark';
    case SOFTWARE_LICENSE = 'software_license';
    case GOODWILL = 'goodwill';
    case COPYRIGHT = 'copyright';
    case FRANCHISE = 'franchise';

    public function label(): string
    {
        return match ($this) {
            self::PATENT => 'Patent',
            self::TRADEMARK => 'Trademark',
            self::SOFTWARE_LICENSE => 'Software License',
            self::GOODWILL => 'Goodwill',
            self::COPYRIGHT => 'Copyright',
            self::FRANCHISE => 'Franchise',
        };
    }

    public function defaultUsefulLifeYears(): ?int
    {
        return match ($this) {
            self::PATENT => 20,
            self::TRADEMARK => 10,
            self::SOFTWARE_LICENSE => 3,
            self::GOODWILL => null, // Indefinite or tested annually
            self::COPYRIGHT => 70, // Life of author + 70
            self::FRANCHISE => 10,
        };
    }

    public function isDefiniteLife(): bool
    {
        return $this !== self::GOODWILL;
    }
}
