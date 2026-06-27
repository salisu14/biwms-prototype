<?php

namespace App\Filament\Resources\PriceChangeTemplates;

use App\Filament\Resources\PriceChangeTemplates\Pages\CreatePriceChangeTemplate;
use App\Filament\Resources\PriceChangeTemplates\Pages\EditPriceChangeTemplate;
use App\Filament\Resources\PriceChangeTemplates\Pages\ListPriceChangeTemplates;
use App\Filament\Resources\PriceChangeTemplates\Pages\ViewPriceChangeTemplate;
use App\Filament\Resources\PriceChangeTemplates\Schemas\PriceChangeTemplateForm;
use App\Filament\Resources\PriceChangeTemplates\Schemas\PriceChangeTemplateInfolist;
use App\Filament\Resources\PriceChangeTemplates\Tables\PriceChangeTemplatesTable;
use App\Models\PriceChangeTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PriceChangeTemplateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'pricing';
    }

    public static function permissionResource(): string
    {
        return 'price_change_template';
    }

    protected static ?string $model = PriceChangeTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PriceChangeTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PriceChangeTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceChangeTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceChangeTemplates::route('/'),
            'create' => CreatePriceChangeTemplate::route('/create'),
            'view' => ViewPriceChangeTemplate::route('/{record}'),
            'edit' => EditPriceChangeTemplate::route('/{record}/edit'),
        ];
    }
}
