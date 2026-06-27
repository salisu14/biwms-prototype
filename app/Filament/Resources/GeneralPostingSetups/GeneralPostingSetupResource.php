<?php

namespace App\Filament\Resources\GeneralPostingSetups;

use App\Filament\Resources\GeneralPostingSetups\Pages\CreateGeneralPostingSetup;
use App\Filament\Resources\GeneralPostingSetups\Pages\EditGeneralPostingSetup;
use App\Filament\Resources\GeneralPostingSetups\Pages\ListGeneralPostingSetups;
use App\Filament\Resources\GeneralPostingSetups\Pages\ViewGeneralPostingSetup;
use App\Filament\Resources\GeneralPostingSetups\Schemas\GeneralPostingSetupForm;
use App\Filament\Resources\GeneralPostingSetups\Schemas\GeneralPostingSetupInfolist;
use App\Filament\Resources\GeneralPostingSetups\Tables\GeneralPostingSetupsTable;
use App\Models\GeneralPostingSetup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GeneralPostingSetupResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'general_posting_setup';
    }

    protected static ?string $model = GeneralPostingSetup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return GeneralPostingSetupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GeneralPostingSetupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralPostingSetupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof GeneralPostingSetup) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'generalBusinessPostingGroup.code',
            'generalProductPostingGroup.code',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var GeneralPostingSetup $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var GeneralPostingSetup $record */
        return [
            'Sales Account' => $record->salesAccount?->name ?? '—',
            'Blocked' => $record->blocked ? 'Yes' : 'No',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'generalBusinessPostingGroup',
            'generalProductPostingGroup',
            'salesAccount',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeneralPostingSetups::route('/'),
            'create' => CreateGeneralPostingSetup::route('/create'),
            'view' => ViewGeneralPostingSetup::route('/{record}'),
            'edit' => EditGeneralPostingSetup::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(GeneralPostingSetup $record): string
    {
        $businessGroupCode = $record->generalBusinessPostingGroup?->code ?? 'Unknown Business Group';
        $productGroupCode = $record->generalProductPostingGroup?->code ?? 'Unknown Product Group';

        return "{$businessGroupCode} / {$productGroupCode}";
    }
}
