<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalModerationSessions;

use App\Filament\Resources\PerformanceAppraisalModerationSessions\Pages\CreatePerformanceAppraisalModerationSession;
use App\Filament\Resources\PerformanceAppraisalModerationSessions\Pages\EditPerformanceAppraisalModerationSession;
use App\Filament\Resources\PerformanceAppraisalModerationSessions\Pages\ListPerformanceAppraisalModerationSessions;
use App\Filament\Resources\PerformanceAppraisalModerationSessions\Pages\ViewPerformanceAppraisalModerationSession;
use App\Filament\Resources\PerformanceAppraisalModerationSessions\Schemas\PerformanceAppraisalModerationSessionForm;
use App\Filament\Resources\PerformanceAppraisalModerationSessions\Schemas\PerformanceAppraisalModerationSessionInfolist;
use App\Filament\Resources\PerformanceAppraisalModerationSessions\Tables\PerformanceAppraisalModerationSessionsTable;
use App\Models\PerformanceAppraisalModerationSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceAppraisalModerationSessionResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_appraisal_moderation_session';
    }

    protected static ?string $model = PerformanceAppraisalModerationSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceAppraisalModerationSessionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceAppraisalModerationSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceAppraisalModerationSessionsTable::configure($table);
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
            'index' => ListPerformanceAppraisalModerationSessions::route('/'),
            'create' => CreatePerformanceAppraisalModerationSession::route('/create'),
            'view' => ViewPerformanceAppraisalModerationSession::route('/{record}'),
            'edit' => EditPerformanceAppraisalModerationSession::route('/{record}/edit'),
        ];
    }
}
