<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceSchedulingRules;

use App\Filament\Resources\WorkforceSchedulingRules\Pages\CreateWorkforceSchedulingRule;
use App\Filament\Resources\WorkforceSchedulingRules\Pages\EditWorkforceSchedulingRule;
use App\Filament\Resources\WorkforceSchedulingRules\Pages\ListWorkforceSchedulingRules;
use App\Filament\Resources\WorkforceSchedulingRules\Pages\ViewWorkforceSchedulingRule;
use App\Filament\Resources\WorkforceSchedulingRules\Schemas\WorkforceSchedulingRuleForm;
use App\Filament\Resources\WorkforceSchedulingRules\Schemas\WorkforceSchedulingRuleInfolist;
use App\Filament\Resources\WorkforceSchedulingRules\Tables\WorkforceSchedulingRulesTable;
use App\Models\WorkforceSchedulingRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceSchedulingRuleResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'workforce_scheduling_rule';
    }

    protected static ?string $model = WorkforceSchedulingRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Scheduling Rules';

    public static function form(Schema $schema): Schema
    {
        return WorkforceSchedulingRuleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceSchedulingRuleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceSchedulingRulesTable::configure($table);
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
            'index' => ListWorkforceSchedulingRules::route('/'),
            'create' => CreateWorkforceSchedulingRule::route('/create'),
            'view' => ViewWorkforceSchedulingRule::route('/{record}'),
            'edit' => EditWorkforceSchedulingRule::route('/{record}/edit'),
        ];
    }
}
