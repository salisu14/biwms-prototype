<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmployeeAssignmentType: string implements HasLabel
{
    case Corporate = 'corporate';
    case Factory = 'factory';

    public function getLabel(): ?string
    {
        return match($this) {
            self::Corporate => 'Corporate staff',
            self::Factory => 'Factory based staff',
        };
    }

    public function isFactoryBased(): bool
    {
        return $this === self::Factory;
    }
}
