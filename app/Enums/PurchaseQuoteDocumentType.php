<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseQuoteDocumentType: string implements HasColor, HasLabel
{
    case QUOTE = 'quote';
    case BLANKET_ORDER = 'blanket_order';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::QUOTE => 'Purchase Quote',
            self::BLANKET_ORDER => 'Blanket Purchase Order',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::QUOTE => 'gray',
            self::BLANKET_ORDER => 'info',
        };
    }
}
