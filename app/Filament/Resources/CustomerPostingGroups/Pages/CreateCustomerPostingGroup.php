<?php

namespace App\Filament\Resources\CustomerPostingGroups\Pages;

use App\Filament\Resources\CustomerPostingGroups\CustomerPostingGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerPostingGroup extends CreateRecord
{
    protected static string $resource = CustomerPostingGroupResource::class;
}
