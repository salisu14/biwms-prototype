<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referrers;

use App\Filament\Resources\Referrers\Pages\CreateReferrer;
use App\Filament\Resources\Referrers\Pages\EditReferrer;
use App\Filament\Resources\Referrers\Pages\ListReferrers;
use App\Filament\Resources\Referrers\Pages\ViewReferrer;
use App\Filament\Resources\Referrers\Schemas\ReferrerForm;
use App\Filament\Resources\Referrers\Schemas\ReferrerInfolist;
use App\Filament\Resources\Referrers\Tables\ReferrersTable;
use App\Models\Referrer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    protected static string|UnitEnum|null $navigationGroup = 'Sales & Marketing';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 72;

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['contact', 'customer', 'vendor', 'employee']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'code',
            'name',
            'phone',
            'email',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Referrer $record */
        return "{$record->code} - {$record->name}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Referrer $record */
        return [
            'Type' => $record->type?->label() ?? '—',
            'Linked Entity' => $record->linkedEntityLabel(),
            'Active' => $record->is_active ? 'Yes' : 'No',
        ];
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
            RelationManagers\ReferredCustomersRelationManager::class,
        ];
    }
}
