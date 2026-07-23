<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\CustomerReferrals\Pages;

use App\Filament\Sales\Resources\CustomerReferrals\CustomerReferralResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerReferral extends ViewRecord
{
    protected static string $resource = CustomerReferralResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
