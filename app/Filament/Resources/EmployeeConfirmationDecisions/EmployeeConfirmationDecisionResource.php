<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeConfirmationDecisions;

use App\Filament\Resources\EmployeeConfirmationDecisions\Pages\CreateEmployeeConfirmationDecision;
use App\Filament\Resources\EmployeeConfirmationDecisions\Pages\EditEmployeeConfirmationDecision;
use App\Filament\Resources\EmployeeConfirmationDecisions\Pages\ListEmployeeConfirmationDecisions;
use App\Filament\Resources\EmployeeConfirmationDecisions\Pages\ViewEmployeeConfirmationDecision;
use App\Filament\Resources\EmployeeConfirmationDecisions\Schemas\EmployeeConfirmationDecisionForm;
use App\Filament\Resources\EmployeeConfirmationDecisions\Schemas\EmployeeConfirmationDecisionInfolist;
use App\Filament\Resources\EmployeeConfirmationDecisions\Tables\EmployeeConfirmationDecisionsTable;
use App\Models\EmployeeConfirmationDecision;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeeConfirmationDecisionResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_confirmation_decision';
    }

    protected static ?string $model = EmployeeConfirmationDecision::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return EmployeeConfirmationDecisionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeConfirmationDecisionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeConfirmationDecisionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeConfirmationDecisions::route('/'),
            'create' => CreateEmployeeConfirmationDecision::route('/create'),
            'view' => ViewEmployeeConfirmationDecision::route('/{record}'),
            'edit' => EditEmployeeConfirmationDecision::route('/{record}/edit'),
        ];
    }
}
