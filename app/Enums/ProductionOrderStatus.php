<?php

namespace App\Enums;

enum ProductionOrderStatus: string
{
    case SIMULATED = 'SIMULATED';
    case PLANNED = 'PLANNED';
    case FIRM_PLANNED = 'FIRM_PLANNED';
    case RELEASED = 'RELEASED';
    case FINISHED = 'FINISHED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::SIMULATED => 'Simulated',
            self::PLANNED => 'Planned',
            self::FIRM_PLANNED => 'Firm Planned',
            self::RELEASED => 'Released',
            self::FINISHED => 'Finished',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SIMULATED => 'Initial planning state, can be freely modified',
            self::PLANNED => 'Planned but not committed',
            self::FIRM_PLANNED => 'Planned with capacity reserved',
            self::RELEASED => 'Released to production floor',
            self::FINISHED => 'Production completed',
            self::CANCELLED => 'Order revoked',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::SIMULATED, self::PLANNED, self::FIRM_PLANNED]);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::FIRM_PLANNED, self::RELEASED]);
    }

    public function isCompleted(): bool
    {
        return $this === self::FINISHED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $validTransitions = [
            self::SIMULATED->value => [self::PLANNED, self::FIRM_PLANNED, self::CANCELLED],
            self::PLANNED->value => [self::FIRM_PLANNED, self::FINISHED, self::CANCELLED],
            self::FIRM_PLANNED->value => [self::RELEASED, self::FINISHED, self::CANCELLED],
            self::RELEASED->value => [self::FINISHED, self::CANCELLED],
            self::FINISHED->value => [self::RELEASED], // To allow REOPEN
            self::CANCELLED->value => [self::PLANNED, self::FIRM_PLANNED, self::RELEASED],
        ];

        return in_array($newStatus, $validTransitions[$this->value] ?? []);
    }
}
