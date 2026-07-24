<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferralCommissionSettings\Pages;

use App\Filament\Resources\ReferralCommissionSettings\Pages\CreateReferralCommissionSetting as BaseCreateReferralCommissionSetting;
use App\Filament\Sales\Resources\ReferralCommissionSettings\ReferralCommissionSettingResource;

class CreateReferralCommissionSetting extends BaseCreateReferralCommissionSetting
{
    protected static string $resource = ReferralCommissionSettingResource::class;
}
