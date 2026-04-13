<?php

declare(strict_types=1);

namespace App\Enums;

enum FAStatus: string
{
    case NEW = 'new';                    // Created, not yet acquired
    case ACTIVE = 'active';              // In use, depreciating
    case UNDER_CONSTRUCTION = 'under_construction'; // CWIP (Capital Work in Progress)
    case DISMANTLED = 'dismantled';      // Removed from service, not yet disposed
    case DISPOSED = 'disposed';          // Fully retired
    case SOLD = 'sold';                  // Sold to external party
    case TRANSFERRED = 'transferred';    // Moved to another entity

    public function canDepreciate(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canDispose(): bool
    {
        return in_array($this, [self::ACTIVE, self::DISMANTLED]);
    }
}
