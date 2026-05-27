<?php

namespace App\Filament\Sales\Resources\Customers\Pages;

use App\Filament\Sales\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
