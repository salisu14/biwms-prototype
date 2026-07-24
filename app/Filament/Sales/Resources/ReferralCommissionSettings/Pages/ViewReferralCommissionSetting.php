<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferralCommissionSettings\Pages;

use App\Filament\Resources\ReferralCommissionSettings\Pages\ViewReferralCommissionSetting as BaseViewReferralCommissionSetting;
use App\Filament\Sales\Resources\ReferralCommissionSettings\ReferralCommissionSettingResource;

class ViewReferralCommissionSetting extends BaseViewReferralCommissionSetting
{
    protected static string $resource = ReferralCommissionSettingResource::class;
}
