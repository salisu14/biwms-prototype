<?php

declare(strict_types=1);

namespace App\Policies;

class WorkCenterCalendarPolicy extends BaseFilamentPolicy
{
    protected string $module = 'factory';

    protected string $resource = 'work_center_calendar';
}
