<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftReplacements;

use App\Filament\Resources\WorkforceShiftReplacements\Pages\CreateWorkforceShiftReplacement;
use App\Filament\Resources\WorkforceShiftReplacements\Pages\EditWorkforceShiftReplacement;
use App\Filament\Resources\WorkforceShiftReplacements\Pages\ListWorkforceShiftReplacements;
use App\Filament\Resources\WorkforceShiftReplacements\Pages\ViewWorkforceShiftReplacement;
use App\Filament\Resources\WorkforceShiftReplacements\Schemas\WorkforceShiftReplacementForm;
use App\Filament\Resources\WorkforceShiftReplacements\Schemas\WorkforceShiftReplacementInfolist;
use App\Filament\Resources\WorkforceShiftReplacements\Tables\WorkforceShiftReplacementsTable;
use App\Models\WorkforceShiftReplacement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceShiftReplacementResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'shift_replacement';
    }

    protected static ?string $model = WorkforceShiftReplacement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Shift Replacements';

    public static function form(Schema $schema): Schema
    {
        return WorkforceShiftReplacementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceShiftReplacementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceShiftReplacementsTable::configure($table);
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
            'index' => ListWorkforceShiftReplacements::route('/'),
            'create' => CreateWorkforceShiftReplacement::route('/create'),
            'view' => ViewWorkforceShiftReplacement::route('/{record}'),
            'edit' => EditWorkforceShiftReplacement::route('/{record}/edit'),
        ];
    }
}
