<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionSettings\Pages;

use App\Filament\Resources\ReferralCommissionSettings\ReferralCommissionSettingResource;
use App\Models\Business;
use App\Services\Sales\ReferralCommissions\ReferralCommissionSettingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReferralCommissionSetting extends CreateRecord
{
    protected static string $resource = ReferralCommissionSettingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ReferralCommissionSettingService::class)->saveForBusiness(
            Business::query()->findOrFail($data['business_id']),
            $data,
            auth()->id(),
        );
    }
}
