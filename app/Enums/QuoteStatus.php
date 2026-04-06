<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum QuoteStatus: string implements HasLabel, HasColor, HasIcon
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent to Customer',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'info',
            self::ACCEPTED => 'success',
            self::REJECTED => 'danger',
            self::EXPIRED => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-m-pencil-square',
            self::SENT => 'heroicon-m-paper-airplane',
            self::ACCEPTED => 'heroicon-m-check-badge',
            self::REJECTED => 'heroicon-m-x-circle',
            self::EXPIRED => 'heroicon-m-clock',
        };
    }

    /**
     * Business Logic: Can this quote be converted into a Sales Order?
     */
    public function canBeConverted(): bool
    {
        return $this === self::ACCEPTED;
    }

    /**
     * Business Logic: Can the quote still be edited?
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT]);
    }
}
