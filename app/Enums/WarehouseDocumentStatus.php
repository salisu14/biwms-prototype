<?php

declare(strict_types=1);

namespace App\Enums;

enum WarehouseDocumentStatus: string
{
    case OPEN = 'open';
    case RELEASED = 'released';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function canProcess(): bool
    {
        return in_array($this, [self::RELEASED, self::IN_PROGRESS]);
    }
}
