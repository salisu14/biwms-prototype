<?php

declare(strict_types=1);

namespace App\Enums;

enum CurrencyAdjustmentType: string
{
    case REVALUATION = 'revaluation';
    case REALIZED_GAIN = 'realized_gain';
    case REALIZED_LOSS = 'realized_loss';
    case UNREALIZED_GAIN = 'unrealized_gain';
    case UNREALIZED_LOSS = 'unrealized_loss';

    public function label(): string
    {
        return match ($this) {
            self::REVALUATION => 'Revaluation',
            self::REALIZED_GAIN => 'Realized Gain',
            self::REALIZED_LOSS => 'Realized Loss',
            self::UNREALIZED_GAIN => 'Unrealized Gain',
            self::UNREALIZED_LOSS => 'Unrealized Loss',
        };
    }

    public function isGain(): bool
    {
        return in_array($this, [self::REALIZED_GAIN, self::UNREALIZED_GAIN], true);
    }
}
