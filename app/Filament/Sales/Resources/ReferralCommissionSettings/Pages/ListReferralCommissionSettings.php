<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferralCommissionSettings\Pages;

use App\Filament\Sales\Resources\ReferralCommissionSettings\ReferralCommissionSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferralCommissionSettings extends ListRecords
{
    protected static string $resource = ReferralCommissionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
