<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferralCommissionSettings\Pages;

use App\Filament\Resources\ReferralCommissionSettings\Pages\EditReferralCommissionSetting as BaseEditReferralCommissionSetting;
use App\Filament\Sales\Resources\ReferralCommissionSettings\ReferralCommissionSettingResource;

class EditReferralCommissionSetting extends BaseEditReferralCommissionSetting
{
    protected static string $resource = ReferralCommissionSettingResource::class;
}
