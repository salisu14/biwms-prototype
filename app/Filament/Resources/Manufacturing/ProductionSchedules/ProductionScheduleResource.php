<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionSchedules;

use App\Filament\Resources\Manufacturing\ProductionSchedules\Pages\CreateProductionSchedule;
use App\Filament\Resources\Manufacturing\ProductionSchedules\Pages\EditProductionSchedule;
use App\Filament\Resources\Manufacturing\ProductionSchedules\Pages\ListProductionSchedules;
use App\Filament\Resources\Manufacturing\ProductionSchedules\Pages\ViewProductionSchedule;
use App\Filament\Resources\Manufacturing\ProductionSchedules\Schemas\ProductionScheduleForm;
use App\Filament\Resources\Manufacturing\ProductionSchedules\Schemas\ProductionScheduleInfolist;
use App\Filament\Resources\Manufacturing\ProductionSchedules\Tables\ProductionSchedulesTable;
use App\Models\Manufacturing\ProductionSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionScheduleResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'manufacturing';
    }

    public static function permissionResource(): string
    {
        return 'production_schedule';
    }

    protected static ?string $model = ProductionSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing Planning';

    protected static ?string $label = 'Production Schedule';

    protected static ?string $pluralLabel = 'Production Schedules';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return ProductionScheduleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductionScheduleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionSchedulesTable::configure($table);
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
            'index' => ListProductionSchedules::route('/'),
            'create' => CreateProductionSchedule::route('/create'),
            'view' => ViewProductionSchedule::route('/{record}'),
            'edit' => EditProductionSchedule::route('/{record}/edit'),
        ];
    }
}
