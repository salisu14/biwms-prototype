<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionPlans\Pages;

use App\Filament\Resources\ReferralCommissionPlans\ReferralCommissionPlanResource;
use App\Services\Sales\ReferralCommissions\ReferralCommissionPlanService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditReferralCommissionPlan extends EditRecord
{
    protected static string $resource = ReferralCommissionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ReferralCommissionPlanService::class)->updateDraft($record, $data, auth()->id());
    }
}
