<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOffers;

use App\Filament\Resources\RecruitmentOffers\Pages\CreateRecruitmentOffer;
use App\Filament\Resources\RecruitmentOffers\Pages\EditRecruitmentOffer;
use App\Filament\Resources\RecruitmentOffers\Pages\ListRecruitmentOffers;
use App\Filament\Resources\RecruitmentOffers\Pages\ViewRecruitmentOffer;
use App\Filament\Resources\RecruitmentOffers\Schemas\RecruitmentOfferForm;
use App\Filament\Resources\RecruitmentOffers\Schemas\RecruitmentOfferInfolist;
use App\Filament\Resources\RecruitmentOffers\Tables\RecruitmentOffersTable;
use App\Models\RecruitmentOffer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentOfferResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_offer';
    }

    protected static ?string $model = RecruitmentOffer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentOfferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentOfferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentOffersTable::configure($table);
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
            'index' => ListRecruitmentOffers::route('/'),
            'create' => CreateRecruitmentOffer::route('/create'),
            'view' => ViewRecruitmentOffer::route('/{record}'),
            'edit' => EditRecruitmentOffer::route('/{record}/edit'),
        ];
    }
}
