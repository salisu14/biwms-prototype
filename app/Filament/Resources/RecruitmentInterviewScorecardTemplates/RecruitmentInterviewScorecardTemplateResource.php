<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewScorecardTemplates;

use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Pages\CreateRecruitmentInterviewScorecardTemplate;
use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Pages\EditRecruitmentInterviewScorecardTemplate;
use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Pages\ListRecruitmentInterviewScorecardTemplates;
use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Pages\ViewRecruitmentInterviewScorecardTemplate;
use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Schemas\RecruitmentInterviewScorecardTemplateForm;
use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Schemas\RecruitmentInterviewScorecardTemplateInfolist;
use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Tables\RecruitmentInterviewScorecardTemplatesTable;
use App\Models\RecruitmentInterviewScorecardTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentInterviewScorecardTemplateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_interview_scorecard_template';
    }

    protected static ?string $model = RecruitmentInterviewScorecardTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentInterviewScorecardTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentInterviewScorecardTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentInterviewScorecardTemplatesTable::configure($table);
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
            'index' => ListRecruitmentInterviewScorecardTemplates::route('/'),
            'create' => CreateRecruitmentInterviewScorecardTemplate::route('/create'),
            'view' => ViewRecruitmentInterviewScorecardTemplate::route('/{record}'),
            'edit' => EditRecruitmentInterviewScorecardTemplate::route('/{record}/edit'),
        ];
    }
}
