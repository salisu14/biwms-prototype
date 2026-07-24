<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferralCommissionPlans;

use App\Filament\Resources\ReferralCommissionPlans\RelationManagers\EligibleCategoriesRelationManager;
use App\Filament\Resources\ReferralCommissionPlans\RelationManagers\EligibleItemsRelationManager;
use App\Filament\Resources\ReferralCommissionPlans\RelationManagers\TiersRelationManager;
use App\Filament\Resources\ReferralCommissionPlans\Schemas\ReferralCommissionPlanForm;
use App\Filament\Resources\ReferralCommissionPlans\Tables\ReferralCommissionPlansTable;
use App\Filament\Sales\Resources\ReferralCommissionPlans\Pages\CreateReferralCommissionPlan;
use App\Filament\Sales\Resources\ReferralCommissionPlans\Pages\EditReferralCommissionPlan;
use App\Filament\Sales\Resources\ReferralCommissionPlans\Pages\ListReferralCommissionPlans;
use App\Filament\Sales\Resources\ReferralCommissionPlans\Pages\ViewReferralCommissionPlan;
use App\Models\ReferralCommissionPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReferralCommissionPlanResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'referral_commission_plan';
    }

    protected static ?string $model = ReferralCommissionPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Referral Commissions';

    public static function form(Schema $schema): Schema
    {
        return ReferralCommissionPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferralCommissionPlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TiersRelationManager::class,
            EligibleItemsRelationManager::class,
            EligibleCategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferralCommissionPlans::route('/'),
            'create' => CreateReferralCommissionPlan::route('/create'),
            'view' => ViewReferralCommissionPlan::route('/{record}'),
            'edit' => EditReferralCommissionPlan::route('/{record}/edit'),
        ];
    }
}
