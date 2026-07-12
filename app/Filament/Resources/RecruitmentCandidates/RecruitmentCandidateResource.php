<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentCandidates;

use App\Filament\Resources\RecruitmentCandidates\Pages\CreateRecruitmentCandidate;
use App\Filament\Resources\RecruitmentCandidates\Pages\EditRecruitmentCandidate;
use App\Filament\Resources\RecruitmentCandidates\Pages\ListRecruitmentCandidates;
use App\Filament\Resources\RecruitmentCandidates\Pages\ViewRecruitmentCandidate;
use App\Filament\Resources\RecruitmentCandidates\Schemas\RecruitmentCandidateForm;
use App\Filament\Resources\RecruitmentCandidates\Schemas\RecruitmentCandidateInfolist;
use App\Filament\Resources\RecruitmentCandidates\Tables\RecruitmentCandidatesTable;
use App\Models\RecruitmentCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentCandidateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_candidate';
    }

    protected static ?string $model = RecruitmentCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentCandidateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentCandidateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentCandidatesTable::configure($table);
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
            'index' => ListRecruitmentCandidates::route('/'),
            'create' => CreateRecruitmentCandidate::route('/create'),
            'view' => ViewRecruitmentCandidate::route('/{record}'),
            'edit' => EditRecruitmentCandidate::route('/{record}/edit'),
        ];
    }
}
