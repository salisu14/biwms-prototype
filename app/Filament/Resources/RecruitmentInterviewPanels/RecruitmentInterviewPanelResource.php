<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewPanels;

use App\Filament\Resources\RecruitmentInterviewPanels\Pages\CreateRecruitmentInterviewPanel;
use App\Filament\Resources\RecruitmentInterviewPanels\Pages\EditRecruitmentInterviewPanel;
use App\Filament\Resources\RecruitmentInterviewPanels\Pages\ListRecruitmentInterviewPanels;
use App\Filament\Resources\RecruitmentInterviewPanels\Pages\ViewRecruitmentInterviewPanel;
use App\Filament\Resources\RecruitmentInterviewPanels\Schemas\RecruitmentInterviewPanelForm;
use App\Filament\Resources\RecruitmentInterviewPanels\Schemas\RecruitmentInterviewPanelInfolist;
use App\Filament\Resources\RecruitmentInterviewPanels\Tables\RecruitmentInterviewPanelsTable;
use App\Models\RecruitmentInterviewPanel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentInterviewPanelResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_interview_panel';
    }

    protected static ?string $model = RecruitmentInterviewPanel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentInterviewPanelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentInterviewPanelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentInterviewPanelsTable::configure($table);
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
            'index' => ListRecruitmentInterviewPanels::route('/'),
            'create' => CreateRecruitmentInterviewPanel::route('/create'),
            'view' => ViewRecruitmentInterviewPanel::route('/{record}'),
            'edit' => EditRecruitmentInterviewPanel::route('/{record}/edit'),
        ];
    }
}
