<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles;

use App\Filament\Resources\PerformanceAppraisalCycles\Pages\CreatePerformanceAppraisalCycle;
use App\Filament\Resources\PerformanceAppraisalCycles\Pages\EditPerformanceAppraisalCycle;
use App\Filament\Resources\PerformanceAppraisalCycles\Pages\ListPerformanceAppraisalCycles;
use App\Filament\Resources\PerformanceAppraisalCycles\Pages\ViewPerformanceAppraisalCycle;
use App\Filament\Resources\PerformanceAppraisalCycles\Schemas\PerformanceAppraisalCycleForm;
use App\Filament\Resources\PerformanceAppraisalCycles\Schemas\PerformanceAppraisalCycleInfolist;
use App\Filament\Resources\PerformanceAppraisalCycles\Tables\PerformanceAppraisalCyclesTable;
use App\Models\PerformanceAppraisalCycle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceAppraisalCycleResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_appraisal_cycle';
    }

    protected static ?string $model = PerformanceAppraisalCycle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceAppraisalCycleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceAppraisalCycleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceAppraisalCyclesTable::configure($table);
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
            'index' => ListPerformanceAppraisalCycles::route('/'),
            'create' => CreatePerformanceAppraisalCycle::route('/create'),
            'view' => ViewPerformanceAppraisalCycle::route('/{record}'),
            'edit' => EditPerformanceAppraisalCycle::route('/{record}/edit'),
        ];
    }
}
