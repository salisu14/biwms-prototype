<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplicationScreenings;

use App\Filament\Resources\RecruitmentApplicationScreenings\Pages\CreateRecruitmentApplicationScreening;
use App\Filament\Resources\RecruitmentApplicationScreenings\Pages\EditRecruitmentApplicationScreening;
use App\Filament\Resources\RecruitmentApplicationScreenings\Pages\ListRecruitmentApplicationScreenings;
use App\Filament\Resources\RecruitmentApplicationScreenings\Pages\ViewRecruitmentApplicationScreening;
use App\Filament\Resources\RecruitmentApplicationScreenings\Schemas\RecruitmentApplicationScreeningForm;
use App\Filament\Resources\RecruitmentApplicationScreenings\Schemas\RecruitmentApplicationScreeningInfolist;
use App\Filament\Resources\RecruitmentApplicationScreenings\Tables\RecruitmentApplicationScreeningsTable;
use App\Models\RecruitmentApplicationScreening;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentApplicationScreeningResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_screening';
    }

    protected static ?string $model = RecruitmentApplicationScreening::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentApplicationScreeningForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentApplicationScreeningInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentApplicationScreeningsTable::configure($table);
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
            'index' => ListRecruitmentApplicationScreenings::route('/'),
            'create' => CreateRecruitmentApplicationScreening::route('/create'),
            'view' => ViewRecruitmentApplicationScreening::route('/{record}'),
            'edit' => EditRecruitmentApplicationScreening::route('/{record}/edit'),
        ];
    }
}
