<?php

declare(strict_types=1);

namespace App\Enums;

enum MaintenanceContractStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case TERMINATED = 'terminated';
    case RENEWAL_PENDING = 'renewal_pending';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canCreateLogs(): bool
    {
        return in_array($this, [self::ACTIVE, self::RENEWAL_PENDING]);
    }
}
