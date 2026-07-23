<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerReferrals;

use App\Filament\Resources\CustomerReferrals\Pages\CreateCustomerReferral;
use App\Filament\Resources\CustomerReferrals\Pages\EditCustomerReferral;
use App\Filament\Resources\CustomerReferrals\Pages\ListCustomerReferrals;
use App\Filament\Resources\CustomerReferrals\Pages\ViewCustomerReferral;
use App\Filament\Resources\CustomerReferrals\Schemas\CustomerReferralForm;
use App\Filament\Resources\CustomerReferrals\Schemas\CustomerReferralInfolist;
use App\Filament\Resources\CustomerReferrals\Tables\CustomerReferralsTable;
use App\Models\CustomerReferral;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CustomerReferralResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'customer_referral';
    }

    protected static ?string $model = CustomerReferral::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|UnitEnum|null $navigationGroup = 'Sales & Marketing';

    protected static ?int $navigationSort = 73;

    public static function form(Schema $schema): Schema
    {
        return CustomerReferralForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerReferralInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerReferralsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerReferrals::route('/'),
            'create' => CreateCustomerReferral::route('/create'),
            'view' => ViewCustomerReferral::route('/{record}'),
            'edit' => EditCustomerReferral::route('/{record}/edit'),
        ];
    }
}
