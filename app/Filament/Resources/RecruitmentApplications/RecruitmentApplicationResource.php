<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplications;

use App\Filament\Resources\RecruitmentApplications\Pages\CreateRecruitmentApplication;
use App\Filament\Resources\RecruitmentApplications\Pages\EditRecruitmentApplication;
use App\Filament\Resources\RecruitmentApplications\Pages\ListRecruitmentApplications;
use App\Filament\Resources\RecruitmentApplications\Pages\ViewRecruitmentApplication;
use App\Filament\Resources\RecruitmentApplications\Schemas\RecruitmentApplicationForm;
use App\Filament\Resources\RecruitmentApplications\Schemas\RecruitmentApplicationInfolist;
use App\Filament\Resources\RecruitmentApplications\Tables\RecruitmentApplicationsTable;
use App\Models\RecruitmentApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentApplicationResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_application';
    }

    protected static ?string $model = RecruitmentApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentApplicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentApplicationsTable::configure($table);
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
            'index' => ListRecruitmentApplications::route('/'),
            'create' => CreateRecruitmentApplication::route('/create'),
            'view' => ViewRecruitmentApplication::route('/{record}'),
            'edit' => EditRecruitmentApplication::route('/{record}/edit'),
        ];
    }
}
