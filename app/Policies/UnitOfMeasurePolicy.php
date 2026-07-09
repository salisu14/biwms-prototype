<?php

declare(strict_types=1);

namespace App\Policies;

class UnitOfMeasurePolicy extends BaseFilamentPolicy
{
    protected string $module = 'unit_of_measures';

    protected string $resource = 'unit_of_measure';
}
