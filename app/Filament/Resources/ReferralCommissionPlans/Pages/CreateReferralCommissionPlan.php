<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionPlans\Pages;

use App\Filament\Resources\ReferralCommissionPlans\ReferralCommissionPlanResource;
use App\Services\Sales\ReferralCommissions\ReferralCommissionPlanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReferralCommissionPlan extends CreateRecord
{
    protected static string $resource = ReferralCommissionPlanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ReferralCommissionPlanService::class)->create($data, auth()->id());
    }
}
