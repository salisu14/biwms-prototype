<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerReferrals\Pages;

use App\Filament\Resources\CustomerReferrals\CustomerReferralResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerReferrals extends ListRecords
{
    protected static string $resource = CustomerReferralResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
