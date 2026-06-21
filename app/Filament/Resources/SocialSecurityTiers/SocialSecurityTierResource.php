<?php

namespace App\Filament\Resources\SocialSecurityTiers;

use App\Filament\Resources\SocialSecurityTiers\Pages\CreateSocialSecurityTier;
use App\Filament\Resources\SocialSecurityTiers\Pages\EditSocialSecurityTier;
use App\Filament\Resources\SocialSecurityTiers\Pages\ListSocialSecurityTiers;
use App\Filament\Resources\SocialSecurityTiers\Pages\ViewSocialSecurityTier;
use App\Filament\Resources\SocialSecurityTiers\Schemas\SocialSecurityTierForm;
use App\Filament\Resources\SocialSecurityTiers\Schemas\SocialSecurityTierInfolist;
use App\Filament\Resources\SocialSecurityTiers\Tables\SocialSecurityTiersTable;
use App\Models\SocialSecurityTier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SocialSecurityTierResource extends Resource
{
    protected static ?string $model = SocialSecurityTier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'tier_code';

    public static function form(Schema $schema): Schema
    {
        return SocialSecurityTierForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SocialSecurityTierInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialSecurityTiersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof SocialSecurityTier) {
            return static::getModelLabel();
        }

        return "{$record->tier_code} - {$record->code}";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialSecurityTiers::route('/'),
            'create' => CreateSocialSecurityTier::route('/create'),
            'view' => ViewSocialSecurityTier::route('/{record}'),
            'edit' => EditSocialSecurityTier::route('/{record}/edit'),
        ];
    }
}
