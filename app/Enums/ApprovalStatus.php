<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\ApprovableStatus;
use Filament\Support\Contracts\HasLabel;

/**
 * Enhanced ApprovalStatus Enum
 *
 * Provides utility methods for labels, UI colors, and
 * collection formatting for frontend components.
 */
enum ApprovalStatus: string implements ApprovableStatus, HasLabel
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case POSTED = 'posted';
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';

    // Aliases used by the generic ApprovalService status mapping
    public const OPEN = self::DRAFT;
    public const PENDING_APPROVAL = self::PENDING;
    public const RELEASED = self::APPROVED;

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::POSTED => 'Posted',
            self::CANCELLED => 'Cancelled',
            self::ARCHIVED => 'Archived',
        };
    }

    /**
     * Get the CSS color classes (Tailwind) for the status badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-slate-100 text-slate-600 border-slate-200',
            self::PENDING => 'bg-amber-100 text-amber-700 border-amber-200',
            self::APPROVED => 'bg-emerald-100 text-emerald-700 border-emerald-200',
            self::REJECTED => 'bg-rose-100 text-rose-700 border-rose-200',
            self::POSTED => 'bg-indigo-100 text-indigo-700 border-indigo-200',
            self::CANCELLED => 'bg-slate-200 text-slate-500 border-slate-300',
            self::ARCHIVED => 'bg-gray-200 text-gray-700 border-gray-300',
        };
    }

    /**
     * Get the Lucide or FontAwesome icon name associated with the status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'edit-3',
            self::PENDING => 'clock',
            self::APPROVED => 'check-circle',
            self::REJECTED => 'x-circle',
            self::POSTED => 'send',
            self::CANCELLED => 'ban',
            self::ARCHIVED => 'archive',
        };
    }

    // ── ApprovableStatus contract ────────────────────────────

    public function canSubmitForApproval(): bool
    {
        return $this === self::DRAFT;
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED], true);
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PENDING;
    }

    public function isReleased(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Returns an array of all options formatted for a select dropdown.
     *
     * @return array<int, array{label: string, value: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(fn ($status) => [
            'label' => $status->getLabel(),
            'value' => $status->value,
            'color' => $status->color(),
            'icon' => $status->icon(),
        ], self::cases());
    }
}
