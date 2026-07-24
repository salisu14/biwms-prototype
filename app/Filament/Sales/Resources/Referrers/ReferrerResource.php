<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\Referrers;

use App\Filament\Resources\Referrers\RelationManagers\CommissionPlanHistoryRelationManager;
use App\Filament\Resources\Referrers\RelationManagers\ReferredCustomersRelationManager;
use App\Filament\Resources\Referrers\Schemas\ReferrerForm;
use App\Filament\Resources\Referrers\Schemas\ReferrerInfolist;
use App\Filament\Resources\Referrers\Tables\ReferrersTable;
use App\Filament\Sales\Resources\Referrers\Pages\CreateReferrer;
use App\Filament\Sales\Resources\Referrers\Pages\EditReferrer;
use App\Filament\Sales\Resources\Referrers\Pages\ListReferrers;
use App\Filament\Sales\Resources\Referrers\Pages\ViewReferrer;
use App\Models\Referrer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReferrerResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'referrer';
    }

    protected static ?string $model = Referrer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('viewAny', Referrer::class);
    }

    public static function form(Schema $schema): Schema
    {
        return ReferrerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReferrerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferrersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferrers::route('/'),
            'create' => CreateReferrer::route('/create'),
            'view' => ViewReferrer::route('/{record}'),
            'edit' => EditReferrer::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ReferredCustomersRelationManager::class,
            CommissionPlanHistoryRelationManager::class,
        ];
    }
}
