<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionPlans\Pages;

use App\Filament\Resources\ReferralCommissionPlans\ReferralCommissionPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferralCommissionPlans extends ListRecords
{
    protected static string $resource = ReferralCommissionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
