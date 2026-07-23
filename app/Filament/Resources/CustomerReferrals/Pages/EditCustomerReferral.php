<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerReferrals\Pages;

use App\Filament\Resources\CustomerReferrals\CustomerReferralResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerReferral extends EditRecord
{
    protected static string $resource = CustomerReferralResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }
}
