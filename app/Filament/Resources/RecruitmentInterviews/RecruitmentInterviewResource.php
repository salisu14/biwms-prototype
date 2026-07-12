<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviews;

use App\Filament\Resources\RecruitmentInterviews\Pages\CreateRecruitmentInterview;
use App\Filament\Resources\RecruitmentInterviews\Pages\EditRecruitmentInterview;
use App\Filament\Resources\RecruitmentInterviews\Pages\ListRecruitmentInterviews;
use App\Filament\Resources\RecruitmentInterviews\Pages\ViewRecruitmentInterview;
use App\Filament\Resources\RecruitmentInterviews\Schemas\RecruitmentInterviewForm;
use App\Filament\Resources\RecruitmentInterviews\Schemas\RecruitmentInterviewInfolist;
use App\Filament\Resources\RecruitmentInterviews\Tables\RecruitmentInterviewsTable;
use App\Models\RecruitmentInterview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentInterviewResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_interview';
    }

    protected static ?string $model = RecruitmentInterview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentInterviewForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentInterviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentInterviewsTable::configure($table);
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
            'index' => ListRecruitmentInterviews::route('/'),
            'create' => CreateRecruitmentInterview::route('/create'),
            'view' => ViewRecruitmentInterview::route('/{record}'),
            'edit' => EditRecruitmentInterview::route('/{record}/edit'),
        ];
    }
}
