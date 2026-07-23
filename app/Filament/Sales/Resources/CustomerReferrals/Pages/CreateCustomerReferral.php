<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\CustomerReferrals\Pages;

use App\Filament\Resources\CustomerReferrals\Pages\CreateCustomerReferral as BaseCreateCustomerReferral;
use App\Filament\Sales\Resources\CustomerReferrals\CustomerReferralResource;

class CreateCustomerReferral extends BaseCreateCustomerReferral
{
    protected static string $resource = CustomerReferralResource::class;
}
