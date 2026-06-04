<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\ApprovableStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Enum for Sales Order Status with capitalized labels
 */
enum SalesOrderStatus: string implements ApprovableStatus, HasColor, HasIcon, HasLabel
{
    case DRAFT = 'DRAFT';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case RELEASED = 'RELEASED';
    case PICKING = 'PICKING';
    case PACKED = 'PACKED';
    case SHIPPED = 'SHIPPED';
    case INVOICED = 'INVOICED';
    case PARTIALLY_INVOICED = 'PARTIALLY_INVOICED';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';

    // Alias used by the generic ApprovalService
    public const OPEN = self::DRAFT;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'DRAFT',
            self::PENDING_APPROVAL => 'PENDING APPROVAL',
            self::APPROVED => 'APPROVED',
            self::RELEASED => 'RELEASED',
            self::PICKING => 'PICKING',
            self::PACKED => 'PACKED',
            self::SHIPPED => 'SHIPPED',
            self::INVOICED => 'INVOICED',
            self::PARTIALLY_INVOICED => 'PARTIALLY INVOICED',
            self::CLOSED => 'CLOSED',
            self::CANCELLED => 'CANCELLED',
        };
    }

    public function label(): ?string
    {
        return $this->getLabel();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_APPROVAL => 'warning',
            self::APPROVED, self::RELEASED => 'info',
            self::PICKING, self::PACKED => 'primary',
            self::SHIPPED, self::INVOICED, self::CLOSED => 'success',
            self::PARTIALLY_INVOICED => 'info',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-m-pencil-square',
            self::PENDING_APPROVAL => 'heroicon-m-clock',
            self::APPROVED => 'heroicon-m-check-circle',
            self::RELEASED => 'heroicon-m-rocket-launch',
            self::PICKING => 'heroicon-m-archive-box',
            self::PACKED => 'heroicon-m-gift',
            self::SHIPPED => 'heroicon-m-truck',
            self::INVOICED, self::CLOSED => 'heroicon-m-check-badge',
            self::CANCELLED => 'heroicon-m-x-circle',
            default => null,
        };
    }

    // ── ApprovableStatus contract ────────────────────────────

    public function canSubmitForApproval(): bool
    {
        return $this === self::DRAFT;
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_APPROVAL], true);
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PENDING_APPROVAL;
    }

    public function isReleased(): bool
    {
        return in_array($this, [self::APPROVED, self::RELEASED], true);
    }
}
