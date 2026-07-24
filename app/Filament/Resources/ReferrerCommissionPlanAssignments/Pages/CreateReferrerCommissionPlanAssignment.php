<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferrerCommissionPlanAssignments\Pages;

use App\Filament\Resources\ReferrerCommissionPlanAssignments\ReferrerCommissionPlanAssignmentResource;
use App\Models\ReferralCommissionPlan;
use App\Models\Referrer;
use App\Services\Sales\ReferralCommissions\ReferrerCommissionPlanAssignmentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReferrerCommissionPlanAssignment extends CreateRecord
{
    protected static string $resource = ReferrerCommissionPlanAssignmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ReferrerCommissionPlanAssignmentService::class)->assign(
            Referrer::query()->findOrFail($data['referrer_id']),
            ReferralCommissionPlan::query()->findOrFail($data['referral_commission_plan_id']),
            $data,
            auth()->id(),
        );
    }
}
