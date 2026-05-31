<?php

namespace App\Filament\Resources\EmployeePromotionHistories;

use App\Filament\Resources\EmployeePromotionHistories\Pages\ListEmployeePromotionHistories;
use App\Filament\Resources\EmployeePromotionHistories\Schemas\EmployeePromotionHistoryForm;
use App\Filament\Resources\EmployeePromotionHistories\Tables\EmployeePromotionHistoriesTable;
use App\Models\EmployeePromotionHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmployeePromotionHistoryResource extends Resource
{
    protected static ?string $model = EmployeePromotionHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Promotions History';

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeePromotionHistoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeePromotionHistoriesTable::configure($table);
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
            'index' => ListEmployeePromotionHistories::route('/'),
        ];
    }
}
