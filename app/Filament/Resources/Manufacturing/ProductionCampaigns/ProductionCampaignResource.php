<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionCampaigns;

use App\Filament\Resources\Manufacturing\ProductionCampaigns\Pages\CreateProductionCampaign;
use App\Filament\Resources\Manufacturing\ProductionCampaigns\Pages\EditProductionCampaign;
use App\Filament\Resources\Manufacturing\ProductionCampaigns\Pages\ListProductionCampaigns;
use App\Filament\Resources\Manufacturing\ProductionCampaigns\Pages\ViewProductionCampaign;
use App\Filament\Resources\Manufacturing\ProductionCampaigns\Schemas\ProductionCampaignForm;
use App\Filament\Resources\Manufacturing\ProductionCampaigns\Schemas\ProductionCampaignInfolist;
use App\Filament\Resources\Manufacturing\ProductionCampaigns\Tables\ProductionCampaignsTable;
use App\Models\Manufacturing\ProductionCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionCampaignResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'manufacturing';
    }

    public static function permissionResource(): string
    {
        return 'production_campaign';
    }

    protected static ?string $model = ProductionCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing Planning';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return ProductionCampaignForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductionCampaignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionCampaignsTable::configure($table);
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
            'index' => ListProductionCampaigns::route('/'),
            'create' => CreateProductionCampaign::route('/create'),
            'view' => ViewProductionCampaign::route('/{record}'),
            'edit' => EditProductionCampaign::route('/{record}/edit'),
        ];
    }
}
