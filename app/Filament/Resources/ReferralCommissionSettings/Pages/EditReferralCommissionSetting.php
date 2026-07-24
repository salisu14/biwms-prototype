<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionSettings\Pages;

use App\Filament\Resources\ReferralCommissionSettings\ReferralCommissionSettingResource;
use App\Services\Sales\ReferralCommissions\ReferralCommissionSettingService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditReferralCommissionSetting extends EditRecord
{
    protected static string $resource = ReferralCommissionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ReferralCommissionSettingService::class)->saveForBusiness(
            $record->business,
            $data,
            auth()->id(),
        );
    }
}
