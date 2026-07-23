<?php

declare(strict_types=1);

namespace App\Policies;

class ContactPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.customer_contact';
    }

    protected function legacyKey(): string
    {
        return 'customer_contact';
    }
}
