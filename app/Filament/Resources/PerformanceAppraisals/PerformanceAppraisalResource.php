<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisals;

use App\Filament\Resources\PerformanceAppraisals\Pages\CreatePerformanceAppraisal;
use App\Filament\Resources\PerformanceAppraisals\Pages\EditPerformanceAppraisal;
use App\Filament\Resources\PerformanceAppraisals\Pages\ListPerformanceAppraisals;
use App\Filament\Resources\PerformanceAppraisals\Pages\ViewPerformanceAppraisal;
use App\Filament\Resources\PerformanceAppraisals\Schemas\PerformanceAppraisalForm;
use App\Filament\Resources\PerformanceAppraisals\Schemas\PerformanceAppraisalInfolist;
use App\Filament\Resources\PerformanceAppraisals\Tables\PerformanceAppraisalsTable;
use App\Models\PerformanceAppraisal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceAppraisalResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_appraisal';
    }

    protected static ?string $model = PerformanceAppraisal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceAppraisalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceAppraisalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceAppraisalsTable::configure($table);
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
            'index' => ListPerformanceAppraisals::route('/'),
            'create' => CreatePerformanceAppraisal::route('/create'),
            'view' => ViewPerformanceAppraisal::route('/{record}'),
            'edit' => EditPerformanceAppraisal::route('/{record}/edit'),
        ];
    }
}
