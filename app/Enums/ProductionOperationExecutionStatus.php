<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductionOperationExecutionStatus: string implements HasColor, HasLabel
{
    case NotStarted = 'not_started';
    case Ready = 'ready';
    case SetupStarted = 'setup_started';
    case SetupPaused = 'setup_paused';
    case SetupCompleted = 'setup_completed';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Submitted = 'submitted';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    public function getLabel(): ?string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NotStarted, self::Ready => 'gray',
            self::SetupStarted, self::Running => 'info',
            self::SetupPaused, self::Paused => 'warning',
            self::SetupCompleted, self::Completed, self::Submitted => 'primary',
            self::Posted => 'success',
            self::Cancelled, self::Reversed => 'danger',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::NotStarted => [self::Ready, self::SetupStarted, self::Cancelled],
            self::Ready => [self::SetupStarted, self::Running, self::Cancelled],
            self::SetupStarted => [self::SetupPaused, self::SetupCompleted, self::Cancelled],
            self::SetupPaused => [self::SetupStarted, self::Cancelled],
            self::SetupCompleted => [self::Running, self::Cancelled],
            self::Running => [self::Paused, self::Completed, self::Cancelled],
            self::Paused => [self::Running, self::Cancelled],
            self::Completed => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Posted, self::Reversed],
            self::Posted => [self::Reversed],
            self::Cancelled, self::Reversed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNextStatuses(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Posted, self::Cancelled, self::Reversed], true);
    }
}
