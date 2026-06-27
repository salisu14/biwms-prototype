<?php

namespace App\Policies;

class PriceListPolicy extends BaseFilamentPolicy
{
    protected string $module = 'pricing';

    protected string $resource = 'price_list';
}
