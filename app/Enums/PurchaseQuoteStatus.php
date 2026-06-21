<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\ApprovableStatus;

enum PurchaseQuoteStatus: string implements ApprovableStatus
{
    case OPEN = 'open';
    case PENDING_APPROVAL = 'pending_approval';
    case RELEASED = 'released';
    case ARCHIVED = 'archived';
    case CONVERTED = 'converted';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::RELEASED => 'Released',
            self::ARCHIVED => 'Archived',
            self::CONVERTED => 'Converted to Order',
            self::CANCELLED => 'Cancelled',
            self::PENDING_APPROVAL => 'Pending Approval',
        };
    }

    public function canConvertToOrder(): bool
    {
        return $this === self::RELEASED;
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::OPEN, self::RELEASED, self::PENDING_APPROVAL], true);
    }

    public function canRelease(): bool
    {
        return $this === self::OPEN;
    }

    public function canReopen(): bool
    {
        return $this === self::RELEASED;
    }

    public function canConvert(): bool
    {
        return $this === self::RELEASED;
    }

    public function canSubmitForApproval(): bool
    {
        return $this === self::OPEN;
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PENDING_APPROVAL;
    }

    public function isReleased(): bool
    {
        return $this === self::RELEASED;
    }
}
