<?php

// app/Enums/LotStatus.php

namespace App\Enums;

enum LotStatus: string
{
    case QUARANTINE = 'QUARANTINE';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
    case RECALLED = 'RECALLED';

    public function label(): string
    {
        return match ($this) {
            self::QUARANTINE => 'Quarantine - Pending QA',
            self::APPROVED => 'Approved - Available',
            self::REJECTED => 'Rejected - Destroy',
            self::EXPIRED => 'Expired - Do Not Use',
            self::RECALLED => 'Recalled - Quarantine',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::QUARANTINE => 'bg-yellow-100 text-yellow-800 border-yellow-400 ring-yellow-400',
            self::APPROVED => 'bg-green-100 text-green-800 border-green-400 ring-green-400',
            self::REJECTED => 'bg-red-100 text-red-800 border-red-400 ring-red-400',
            self::EXPIRED => 'bg-gray-100 text-gray-800 border-gray-400 ring-gray-400',
            self::RECALLED => 'bg-purple-100 text-purple-800 border-purple-400 ring-purple-400',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::QUARANTINE => 'clock',
            self::APPROVED => 'check-circle',
            self::REJECTED => 'times-circle',
            self::EXPIRED => 'calendar-times',
            self::RECALLED => 'exclamation-triangle',
        };
    }

    /**
     * Whether this lot can be used in production
     */
    public function isUsable(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Whether this lot is blocked from any use
     */
    public function isBlocked(): bool
    {
        return in_array($this, [self::QUARANTINE, self::REJECTED, self::EXPIRED, self::RECALLED]);
    }

    /**
     * Whether QA can change this status
     */
    public function isQaControllable(): bool
    {
        return in_array($this, [self::QUARANTINE, self::APPROVED]);
    }

    /**
     * Required disposition action
     */
    public function dispositionAction(): string
    {
        return match ($this) {
            self::QUARANTINE => 'Awaiting QA Review',
            self::APPROVED => 'Available for Use',
            self::REJECTED => 'Schedule Destruction',
            self::EXPIRED => 'Retest or Destroy',
            self::RECALLED => 'Investigate & Report',
        };
    }

    /**
     * Get allowed status transitions
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::QUARANTINE => [self::APPROVED, self::REJECTED],
            self::APPROVED => [self::RECALLED, self::EXPIRED],
            self::REJECTED => [], // Terminal
            self::EXPIRED => [self::REJECTED], // Can confirm destruction
            self::RECALLED => [self::QUARANTINE, self::REJECTED],
        };
    }

    /**
     * Regulatory impact level
     */
    public function regulatoryImpact(): string
    {
        return match ($this) {
            self::QUARANTINE, self::APPROVED => 'Normal',
            self::REJECTED, self::EXPIRED => 'Medium',
            self::RECALLED => 'High - FDA Reportable',
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
                'usable' => $case->isUsable(),
                'blocked' => $case->isBlocked(),
                'disposition' => $case->dispositionAction(),
            ])
            ->toArray();
    }
}
