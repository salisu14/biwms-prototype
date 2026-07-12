<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTemplates;

use App\Filament\Resources\RecruitmentOnboardingTemplates\Pages\CreateRecruitmentOnboardingTemplate;
use App\Filament\Resources\RecruitmentOnboardingTemplates\Pages\EditRecruitmentOnboardingTemplate;
use App\Filament\Resources\RecruitmentOnboardingTemplates\Pages\ListRecruitmentOnboardingTemplates;
use App\Filament\Resources\RecruitmentOnboardingTemplates\Pages\ViewRecruitmentOnboardingTemplate;
use App\Filament\Resources\RecruitmentOnboardingTemplates\Schemas\RecruitmentOnboardingTemplateForm;
use App\Filament\Resources\RecruitmentOnboardingTemplates\Schemas\RecruitmentOnboardingTemplateInfolist;
use App\Filament\Resources\RecruitmentOnboardingTemplates\Tables\RecruitmentOnboardingTemplatesTable;
use App\Models\RecruitmentOnboardingTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentOnboardingTemplateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_onboarding_template';
    }

    protected static ?string $model = RecruitmentOnboardingTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentOnboardingTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentOnboardingTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentOnboardingTemplatesTable::configure($table);
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
            'index' => ListRecruitmentOnboardingTemplates::route('/'),
            'create' => CreateRecruitmentOnboardingTemplate::route('/create'),
            'view' => ViewRecruitmentOnboardingTemplate::route('/{record}'),
            'edit' => EditRecruitmentOnboardingTemplate::route('/{record}/edit'),
        ];
    }
}
