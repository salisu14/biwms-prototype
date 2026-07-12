<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentAssessments;

use App\Filament\Resources\RecruitmentAssessments\Pages\CreateRecruitmentAssessment;
use App\Filament\Resources\RecruitmentAssessments\Pages\EditRecruitmentAssessment;
use App\Filament\Resources\RecruitmentAssessments\Pages\ListRecruitmentAssessments;
use App\Filament\Resources\RecruitmentAssessments\Pages\ViewRecruitmentAssessment;
use App\Filament\Resources\RecruitmentAssessments\Schemas\RecruitmentAssessmentForm;
use App\Filament\Resources\RecruitmentAssessments\Schemas\RecruitmentAssessmentInfolist;
use App\Filament\Resources\RecruitmentAssessments\Tables\RecruitmentAssessmentsTable;
use App\Models\RecruitmentAssessment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentAssessmentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_assessment';
    }

    protected static ?string $model = RecruitmentAssessment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentAssessmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentAssessmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentAssessmentsTable::configure($table);
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
            'index' => ListRecruitmentAssessments::route('/'),
            'create' => CreateRecruitmentAssessment::route('/create'),
            'view' => ViewRecruitmentAssessment::route('/{record}'),
            'edit' => EditRecruitmentAssessment::route('/{record}/edit'),
        ];
    }
}
