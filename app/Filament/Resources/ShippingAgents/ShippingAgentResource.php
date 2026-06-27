<?php

namespace App\Filament\Resources\ShippingAgents;

use App\Filament\Resources\ShippingAgents\Pages\CreateShippingAgent;
use App\Filament\Resources\ShippingAgents\Pages\EditShippingAgent;
use App\Filament\Resources\ShippingAgents\Pages\ListShippingAgents;
use App\Filament\Resources\ShippingAgents\Pages\ViewShippingAgent;
use App\Filament\Resources\ShippingAgents\Schemas\ShippingAgentForm;
use App\Filament\Resources\ShippingAgents\Schemas\ShippingAgentInfolist;
use App\Filament\Resources\ShippingAgents\Tables\ShippingAgentsTable;
use App\Models\ShippingAgent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShippingAgentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'shipping_agents';
    }

    public static function permissionResource(): string
    {
        return 'shipping_agent';
    }

    protected static ?string $model = ShippingAgent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ShippingAgentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ShippingAgentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingAgentsTable::configure($table);
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
            'index' => ListShippingAgents::route('/'),
            'create' => CreateShippingAgent::route('/create'),
            'view' => ViewShippingAgent::route('/{record}'),
            'edit' => EditShippingAgent::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
