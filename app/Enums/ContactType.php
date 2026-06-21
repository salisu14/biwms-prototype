<?php

namespace App\Enums;

enum ContactType: string
{
    case PERSON = 'person';
    case COMPANY = 'company';

    public function label(): string
    {
        return match ($this) {
            self::PERSON => 'Individual Person',
            self::COMPANY => 'Legal Entity / Company',
        };
    }
}
