<?php

namespace App\Filament\Resources\WorkCenterCalendars;

use App\Filament\Resources\WorkCenterCalendars\Pages\CreateWorkCenterCalendar;
use App\Filament\Resources\WorkCenterCalendars\Pages\EditWorkCenterCalendar;
use App\Filament\Resources\WorkCenterCalendars\Pages\ListWorkCenterCalendars;
use App\Filament\Resources\WorkCenterCalendars\Pages\ViewWorkCenterCalendar;
use App\Filament\Resources\WorkCenterCalendars\Schemas\WorkCenterCalendarForm;
use App\Filament\Resources\WorkCenterCalendars\Schemas\WorkCenterCalendarInfolist;
use App\Filament\Resources\WorkCenterCalendars\Tables\WorkCenterCalendarsTable;
use App\Models\Manufacturing\WorkCenterCalendar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkCenterCalendarResource extends Resource
{
    protected static ?string $model = WorkCenterCalendar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WorkCenterCalendarForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkCenterCalendarInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkCenterCalendarsTable::configure($table);
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
            'index' => ListWorkCenterCalendars::route('/'),
            'create' => CreateWorkCenterCalendar::route('/create'),
            'view' => ViewWorkCenterCalendar::route('/{record}'),
            'edit' => EditWorkCenterCalendar::route('/{record}/edit'),
        ];
    }
}
