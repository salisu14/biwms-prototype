<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PriceList;

class PriceListPolicy extends BaseFilamentPolicy
{
    protected string $permissionPrefix = 'price_list';
}
