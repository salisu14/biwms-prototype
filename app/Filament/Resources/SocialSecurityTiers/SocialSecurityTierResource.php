<?php

namespace App\Filament\Resources\SocialSecurityTiers;

use App\Filament\Resources\SocialSecurityTiers\Pages\CreateSocialSecurityTier;
use App\Filament\Resources\SocialSecurityTiers\Pages\EditSocialSecurityTier;
use App\Filament\Resources\SocialSecurityTiers\Pages\ListSocialSecurityTiers;
use App\Filament\Resources\SocialSecurityTiers\Schemas\SocialSecurityTierForm;
use App\Filament\Resources\SocialSecurityTiers\Tables\SocialSecurityTiersTable;
use App\Models\SocialSecurityTier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SocialSecurityTierResource extends Resource
{
    protected static ?string $model = SocialSecurityTier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SocialSecurityTierForm::configure($schema);
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

    public static function getPages(): array
    {
        return [
            'index' => ListSocialSecurityTiers::route('/'),
            'create' => CreateSocialSecurityTier::route('/create'),
            'edit' => EditSocialSecurityTier::route('/{record}/edit'),
        ];
    }
}
