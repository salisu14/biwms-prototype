<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentHistories;

use App\Filament\Resources\RecruitmentHistories\Pages\ListRecruitmentHistories;
use App\Filament\Resources\RecruitmentHistories\Pages\ViewRecruitmentHistory;
use App\Filament\Resources\RecruitmentHistories\Schemas\RecruitmentHistoryInfolist;
use App\Filament\Resources\RecruitmentHistories\Tables\RecruitmentHistoriesTable;
use App\Models\RecruitmentHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentHistoryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_history';
    }

    protected static ?string $model = RecruitmentHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    protected static ?string $navigationLabel = 'Recruitment History';

    protected static ?int $navigationSort = 99;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentHistoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecruitmentHistories::route('/'),
            'view' => ViewRecruitmentHistory::route('/{record}'),
        ];
    }
}
