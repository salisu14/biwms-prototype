<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionSettings\Pages;

use App\Filament\Resources\ReferralCommissionSettings\ReferralCommissionSettingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReferralCommissionSetting extends ViewRecord
{
    protected static string $resource = ReferralCommissionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
