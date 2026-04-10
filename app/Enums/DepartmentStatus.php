<?php

declare(strict_types=1);

namespace App\Enums;

enum DepartmentStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::CLOSED => 'Closed',
        };
    }

    public function canPost(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canHaveEmployees(): bool
    {
        return in_array($this, [self::ACTIVE, self::INACTIVE], true);
    }
}
