<?php

declare(strict_types=1);

namespace App\Policies;

class BusinessPolicy extends BaseFilamentPolicy
{
    protected string $module = 'businesses';

    protected string $resource = 'business';
}
