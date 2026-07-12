<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentJobPostings;

use App\Filament\Resources\RecruitmentJobPostings\Pages\CreateRecruitmentJobPosting;
use App\Filament\Resources\RecruitmentJobPostings\Pages\EditRecruitmentJobPosting;
use App\Filament\Resources\RecruitmentJobPostings\Pages\ListRecruitmentJobPostings;
use App\Filament\Resources\RecruitmentJobPostings\Pages\ViewRecruitmentJobPosting;
use App\Filament\Resources\RecruitmentJobPostings\Schemas\RecruitmentJobPostingForm;
use App\Filament\Resources\RecruitmentJobPostings\Schemas\RecruitmentJobPostingInfolist;
use App\Filament\Resources\RecruitmentJobPostings\Tables\RecruitmentJobPostingsTable;
use App\Models\RecruitmentJobPosting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentJobPostingResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_job_posting';
    }

    protected static ?string $model = RecruitmentJobPosting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentJobPostingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentJobPostingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentJobPostingsTable::configure($table);
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
            'index' => ListRecruitmentJobPostings::route('/'),
            'create' => CreateRecruitmentJobPosting::route('/create'),
            'view' => ViewRecruitmentJobPosting::route('/{record}'),
            'edit' => EditRecruitmentJobPosting::route('/{record}/edit'),
        ];
    }
}
