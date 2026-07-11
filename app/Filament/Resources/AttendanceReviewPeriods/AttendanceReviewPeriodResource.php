<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewPeriods;

use App\Filament\Resources\AttendanceReviewPeriods\Pages\CreateAttendanceReviewPeriod;
use App\Filament\Resources\AttendanceReviewPeriods\Pages\EditAttendanceReviewPeriod;
use App\Filament\Resources\AttendanceReviewPeriods\Pages\ListAttendanceReviewPeriods;
use App\Filament\Resources\AttendanceReviewPeriods\Pages\ViewAttendanceReviewPeriod;
use App\Filament\Resources\AttendanceReviewPeriods\Schemas\AttendanceReviewPeriodForm;
use App\Filament\Resources\AttendanceReviewPeriods\Schemas\AttendanceReviewPeriodInfolist;
use App\Filament\Resources\AttendanceReviewPeriods\Tables\AttendanceReviewPeriodsTable;
use App\Models\AttendanceReviewPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttendanceReviewPeriodResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'attendance_review_period';
    }

    protected static ?string $model = AttendanceReviewPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Attendance Review Periods';

    public static function form(Schema $schema): Schema
    {
        return AttendanceReviewPeriodForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceReviewPeriodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceReviewPeriodsTable::configure($table);
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
            'index' => ListAttendanceReviewPeriods::route('/'),
            'create' => CreateAttendanceReviewPeriod::route('/create'),
            'view' => ViewAttendanceReviewPeriod::route('/{record}'),
            'edit' => EditAttendanceReviewPeriod::route('/{record}/edit'),
        ];
    }
}
