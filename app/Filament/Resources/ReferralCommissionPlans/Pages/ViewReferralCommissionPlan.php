<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionPlans\Pages;

use App\Filament\Resources\ReferralCommissionPlans\ReferralCommissionPlanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReferralCommissionPlan extends ViewRecord
{
    protected static string $resource = ReferralCommissionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
