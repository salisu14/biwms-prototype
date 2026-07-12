<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentScreeningTemplates;

use App\Filament\Resources\RecruitmentScreeningTemplates\Pages\CreateRecruitmentScreeningTemplate;
use App\Filament\Resources\RecruitmentScreeningTemplates\Pages\EditRecruitmentScreeningTemplate;
use App\Filament\Resources\RecruitmentScreeningTemplates\Pages\ListRecruitmentScreeningTemplates;
use App\Filament\Resources\RecruitmentScreeningTemplates\Pages\ViewRecruitmentScreeningTemplate;
use App\Filament\Resources\RecruitmentScreeningTemplates\Schemas\RecruitmentScreeningTemplateForm;
use App\Filament\Resources\RecruitmentScreeningTemplates\Schemas\RecruitmentScreeningTemplateInfolist;
use App\Filament\Resources\RecruitmentScreeningTemplates\Tables\RecruitmentScreeningTemplatesTable;
use App\Models\RecruitmentScreeningTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentScreeningTemplateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_screening_template';
    }

    protected static ?string $model = RecruitmentScreeningTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentScreeningTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentScreeningTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentScreeningTemplatesTable::configure($table);
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
            'index' => ListRecruitmentScreeningTemplates::route('/'),
            'create' => CreateRecruitmentScreeningTemplate::route('/create'),
            'view' => ViewRecruitmentScreeningTemplate::route('/{record}'),
            'edit' => EditRecruitmentScreeningTemplate::route('/{record}/edit'),
        ];
    }
}
