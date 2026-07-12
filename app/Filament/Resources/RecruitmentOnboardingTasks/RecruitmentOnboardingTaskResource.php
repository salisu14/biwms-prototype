<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTasks;

use App\Filament\Resources\RecruitmentOnboardingTasks\Pages\CreateRecruitmentOnboardingTask;
use App\Filament\Resources\RecruitmentOnboardingTasks\Pages\EditRecruitmentOnboardingTask;
use App\Filament\Resources\RecruitmentOnboardingTasks\Pages\ListRecruitmentOnboardingTasks;
use App\Filament\Resources\RecruitmentOnboardingTasks\Pages\ViewRecruitmentOnboardingTask;
use App\Filament\Resources\RecruitmentOnboardingTasks\Schemas\RecruitmentOnboardingTaskForm;
use App\Filament\Resources\RecruitmentOnboardingTasks\Schemas\RecruitmentOnboardingTaskInfolist;
use App\Filament\Resources\RecruitmentOnboardingTasks\Tables\RecruitmentOnboardingTasksTable;
use App\Models\RecruitmentOnboardingTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentOnboardingTaskResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_onboarding_task';
    }

    protected static ?string $model = RecruitmentOnboardingTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentOnboardingTaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentOnboardingTaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentOnboardingTasksTable::configure($table);
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
            'index' => ListRecruitmentOnboardingTasks::route('/'),
            'create' => CreateRecruitmentOnboardingTask::route('/create'),
            'view' => ViewRecruitmentOnboardingTask::route('/{record}'),
            'edit' => EditRecruitmentOnboardingTask::route('/{record}/edit'),
        ];
    }
}
