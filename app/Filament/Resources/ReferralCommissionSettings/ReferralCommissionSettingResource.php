<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionSettings;

use App\Filament\Resources\ReferralCommissionSettings\Pages\CreateReferralCommissionSetting;
use App\Filament\Resources\ReferralCommissionSettings\Pages\EditReferralCommissionSetting;
use App\Filament\Resources\ReferralCommissionSettings\Pages\ListReferralCommissionSettings;
use App\Filament\Resources\ReferralCommissionSettings\Pages\ViewReferralCommissionSetting;
use App\Filament\Resources\ReferralCommissionSettings\Schemas\ReferralCommissionSettingForm;
use App\Filament\Resources\ReferralCommissionSettings\Tables\ReferralCommissionSettingsTable;
use App\Models\ReferralCommissionSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReferralCommissionSettingResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'referral_commission_setting';
    }

    protected static ?string $model = ReferralCommissionSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales & Marketing';

    protected static ?string $navigationLabel = 'Referral Commission Settings';

    public static function form(Schema $schema): Schema
    {
        return ReferralCommissionSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferralCommissionSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferralCommissionSettings::route('/'),
            'create' => CreateReferralCommissionSetting::route('/create'),
            'view' => ViewReferralCommissionSetting::route('/{record}'),
            'edit' => EditReferralCommissionSetting::route('/{record}/edit'),
        ];
    }
}
