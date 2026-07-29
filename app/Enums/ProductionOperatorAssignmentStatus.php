<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductionOperatorAssignmentStatus: string implements HasColor, HasLabel
{
    case Unassigned = 'unassigned';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unassigned => 'gray',
            self::Assigned, self::Accepted => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
