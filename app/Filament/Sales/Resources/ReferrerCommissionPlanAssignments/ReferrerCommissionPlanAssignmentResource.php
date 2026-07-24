<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferrerCommissionPlanAssignments;

use App\Filament\Resources\ReferrerCommissionPlanAssignments\Schemas\ReferrerCommissionPlanAssignmentForm;
use App\Filament\Resources\ReferrerCommissionPlanAssignments\Tables\ReferrerCommissionPlanAssignmentsTable;
use App\Filament\Sales\Resources\ReferrerCommissionPlanAssignments\Pages\CreateReferrerCommissionPlanAssignment;
use App\Filament\Sales\Resources\ReferrerCommissionPlanAssignments\Pages\ListReferrerCommissionPlanAssignments;
use App\Filament\Sales\Resources\ReferrerCommissionPlanAssignments\Pages\ViewReferrerCommissionPlanAssignment;
use App\Models\ReferrerCommissionPlanAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReferrerCommissionPlanAssignmentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'referrer_commission_plan_assignment';
    }

    protected static ?string $model = ReferrerCommissionPlanAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Referral Commissions';

    public static function form(Schema $schema): Schema
    {
        return ReferrerCommissionPlanAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferrerCommissionPlanAssignmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferrerCommissionPlanAssignments::route('/'),
            'create' => CreateReferrerCommissionPlanAssignment::route('/create'),
            'view' => ViewReferrerCommissionPlanAssignment::route('/{record}'),
        ];
    }
}
