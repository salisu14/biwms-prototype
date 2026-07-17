<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories;

use App\Filament\Resources\PerformanceAppraisalHistories\Pages\ListPerformanceAppraisalHistories;
use App\Filament\Resources\PerformanceAppraisalHistories\Pages\ViewPerformanceAppraisalHistory;
use App\Filament\Resources\PerformanceAppraisalHistories\Schemas\PerformanceAppraisalHistoryForm;
use App\Filament\Resources\PerformanceAppraisalHistories\Schemas\PerformanceAppraisalHistoryInfolist;
use App\Filament\Resources\PerformanceAppraisalHistories\Tables\PerformanceAppraisalHistoriesTable;
use App\Models\PerformanceAppraisalHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceAppraisalHistoryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_history';
    }

    protected static ?string $model = PerformanceAppraisalHistory::class;

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    protected static ?string $navigationLabel = 'Appraisal History';

    protected static ?int $navigationSort = 99;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PerformanceAppraisalHistoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceAppraisalHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceAppraisalHistoriesTable::configure($table);
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
            'index' => ListPerformanceAppraisalHistories::route('/'),
            'view' => ViewPerformanceAppraisalHistory::route('/{record}'),
        ];
    }
}
