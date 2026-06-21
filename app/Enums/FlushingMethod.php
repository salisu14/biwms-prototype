<?php

declare(strict_types=1);

namespace App\Enums;

enum FlushingMethod: string
{
    case MANUAL = 'manual';
    case PICK = 'pick';
    case FORWARD = 'forward';
    case BACKWARD = 'backward';
    case CONSUME = 'consume';

    public function requiresPickDocument(): bool
    {
        return in_array($this, [self::PICK, self::FORWARD, self::BACKWARD]);
    }
}
