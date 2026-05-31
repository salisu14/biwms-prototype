<?php

namespace App\Enums;

use App\Contracts\ApprovableStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PayrollStatus: string implements ApprovableStatus, HasColor, HasLabel
{
    case OPEN = 'OPEN';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case CALCULATED = 'CALCULATED';
    case APPROVED = 'APPROVED';
    case POSTED = 'POSTED';
    case VOIDED = 'VOIDED';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::CALCULATED => 'Calculated',
            self::APPROVED => 'Approved',
            self::POSTED => 'Posted',
            self::VOIDED => 'Voided',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'gray',
            self::PENDING_APPROVAL => 'warning',
            self::CALCULATED => 'info',
            self::APPROVED => 'warning',
            self::POSTED => 'success',
            self::VOIDED => 'danger',
        };
    }

    public function canSubmitForApproval(): bool
    {
        return in_array($this, [self::OPEN, self::CALCULATED], true);
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::OPEN, self::CALCULATED], true);
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PENDING_APPROVAL;
    }

    public function isReleased(): bool
    {
        return $this === self::APPROVED;
    }
}
