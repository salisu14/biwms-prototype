<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentVacancies;

use App\Filament\Resources\RecruitmentVacancies\Pages\CreateRecruitmentVacancy;
use App\Filament\Resources\RecruitmentVacancies\Pages\EditRecruitmentVacancy;
use App\Filament\Resources\RecruitmentVacancies\Pages\ListRecruitmentVacancies;
use App\Filament\Resources\RecruitmentVacancies\Pages\ViewRecruitmentVacancy;
use App\Filament\Resources\RecruitmentVacancies\Schemas\RecruitmentVacancyForm;
use App\Filament\Resources\RecruitmentVacancies\Schemas\RecruitmentVacancyInfolist;
use App\Filament\Resources\RecruitmentVacancies\Tables\RecruitmentVacanciesTable;
use App\Models\RecruitmentVacancy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentVacancyResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_vacancy';
    }

    protected static ?string $model = RecruitmentVacancy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentVacancyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentVacancyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentVacanciesTable::configure($table);
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
            'index' => ListRecruitmentVacancies::route('/'),
            'create' => CreateRecruitmentVacancy::route('/create'),
            'view' => ViewRecruitmentVacancy::route('/{record}'),
            'edit' => EditRecruitmentVacancy::route('/{record}/edit'),
        ];
    }
}
