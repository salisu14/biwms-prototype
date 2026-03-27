<?php
// app/Enums/DocumentStatus.php

namespace App\Enums;

enum DocumentStatus: string
{
    case OPEN = 'OPEN';
    case POSTED = 'POSTED';
    case CANCELLED = 'CANCELLED';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::POSTED => 'Posted',
            self::CANCELLED => 'Cancelled',
            self::PENDING_APPROVAL => 'Pending Approval',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::OPEN => 'bg-blue-100 text-blue-800 border-blue-200',
            self::POSTED => 'bg-green-100 text-green-800 border-green-200',
            self::CANCELLED => 'bg-red-100 text-red-800 border-red-200',
            self::PENDING_APPROVAL => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::OPEN => 'folder-open',
            self::POSTED => 'check-circle',
            self::CANCELLED => 'times-circle',
            self::PENDING_APPROVAL => 'clock',
        };
    }

    /**
     * Whether document can be edited
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::OPEN, self::PENDING_APPROVAL]);
    }

    /**
     * Whether document can be posted
     */
    public function canBePosted(): bool
    {
        return $this === self::OPEN;
    }

    /**
     * Get workflow transitions
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::OPEN => [self::POSTED, self::CANCELLED, self::PENDING_APPROVAL],
            self::PENDING_APPROVAL => [self::OPEN, self::POSTED, self::CANCELLED],
            self::POSTED => [self::CANCELLED], // Reversal
            self::CANCELLED => [], // Terminal state
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
                'editable' => $case->isEditable(),
            ])
            ->toArray();
    }
}
