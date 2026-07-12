<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingPlans;

use App\Filament\Resources\RecruitmentOnboardingPlans\Pages\CreateRecruitmentOnboardingPlan;
use App\Filament\Resources\RecruitmentOnboardingPlans\Pages\EditRecruitmentOnboardingPlan;
use App\Filament\Resources\RecruitmentOnboardingPlans\Pages\ListRecruitmentOnboardingPlans;
use App\Filament\Resources\RecruitmentOnboardingPlans\Pages\ViewRecruitmentOnboardingPlan;
use App\Filament\Resources\RecruitmentOnboardingPlans\Schemas\RecruitmentOnboardingPlanForm;
use App\Filament\Resources\RecruitmentOnboardingPlans\Schemas\RecruitmentOnboardingPlanInfolist;
use App\Filament\Resources\RecruitmentOnboardingPlans\Tables\RecruitmentOnboardingPlansTable;
use App\Models\RecruitmentOnboardingPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentOnboardingPlanResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_onboarding_plan';
    }

    protected static ?string $model = RecruitmentOnboardingPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentOnboardingPlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentOnboardingPlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentOnboardingPlansTable::configure($table);
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
            'index' => ListRecruitmentOnboardingPlans::route('/'),
            'create' => CreateRecruitmentOnboardingPlan::route('/create'),
            'view' => ViewRecruitmentOnboardingPlan::route('/{record}'),
            'edit' => EditRecruitmentOnboardingPlan::route('/{record}/edit'),
        ];
    }
}
