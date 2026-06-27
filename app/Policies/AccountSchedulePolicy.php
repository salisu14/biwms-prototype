<?php

namespace App\Policies;

class AccountSchedulePolicy extends BaseFilamentPolicy
{
    protected string $module = 'finance';

    protected string $resource = 'account_schedule';
}
