<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentPreEmploymentChecks;

use App\Filament\Resources\RecruitmentPreEmploymentChecks\Pages\CreateRecruitmentPreEmploymentCheck;
use App\Filament\Resources\RecruitmentPreEmploymentChecks\Pages\EditRecruitmentPreEmploymentCheck;
use App\Filament\Resources\RecruitmentPreEmploymentChecks\Pages\ListRecruitmentPreEmploymentChecks;
use App\Filament\Resources\RecruitmentPreEmploymentChecks\Pages\ViewRecruitmentPreEmploymentCheck;
use App\Filament\Resources\RecruitmentPreEmploymentChecks\Schemas\RecruitmentPreEmploymentCheckForm;
use App\Filament\Resources\RecruitmentPreEmploymentChecks\Schemas\RecruitmentPreEmploymentCheckInfolist;
use App\Filament\Resources\RecruitmentPreEmploymentChecks\Tables\RecruitmentPreEmploymentChecksTable;
use App\Models\RecruitmentPreEmploymentCheck;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentPreEmploymentCheckResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_pre_employment_check';
    }

    protected static ?string $model = RecruitmentPreEmploymentCheck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentPreEmploymentCheckForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentPreEmploymentCheckInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentPreEmploymentChecksTable::configure($table);
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
            'index' => ListRecruitmentPreEmploymentChecks::route('/'),
            'create' => CreateRecruitmentPreEmploymentCheck::route('/create'),
            'view' => ViewRecruitmentPreEmploymentCheck::route('/{record}'),
            'edit' => EditRecruitmentPreEmploymentCheck::route('/{record}/edit'),
        ];
    }
}
