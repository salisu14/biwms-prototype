<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatchLines;

use App\Filament\Resources\AttendancePayrollReviewBatchLines\Pages\CreateAttendancePayrollReviewBatchLine;
use App\Filament\Resources\AttendancePayrollReviewBatchLines\Pages\EditAttendancePayrollReviewBatchLine;
use App\Filament\Resources\AttendancePayrollReviewBatchLines\Pages\ListAttendancePayrollReviewBatchLines;
use App\Filament\Resources\AttendancePayrollReviewBatchLines\Pages\ViewAttendancePayrollReviewBatchLine;
use App\Filament\Resources\AttendancePayrollReviewBatchLines\Schemas\AttendancePayrollReviewBatchLineForm;
use App\Filament\Resources\AttendancePayrollReviewBatchLines\Schemas\AttendancePayrollReviewBatchLineInfolist;
use App\Filament\Resources\AttendancePayrollReviewBatchLines\Tables\AttendancePayrollReviewBatchLinesTable;
use App\Models\AttendancePayrollReviewBatchLine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttendancePayrollReviewBatchLineResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'payroll';
    }

    public static function permissionResource(): string
    {
        return 'attendance_adjustment';
    }

    protected static ?string $model = AttendancePayrollReviewBatchLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Attendance Adjustments';

    public static function form(Schema $schema): Schema
    {
        return AttendancePayrollReviewBatchLineForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendancePayrollReviewBatchLineInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendancePayrollReviewBatchLinesTable::configure($table);
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
            'index' => ListAttendancePayrollReviewBatchLines::route('/'),
            'create' => CreateAttendancePayrollReviewBatchLine::route('/create'),
            'view' => ViewAttendancePayrollReviewBatchLine::route('/{record}'),
            'edit' => EditAttendancePayrollReviewBatchLine::route('/{record}/edit'),
        ];
    }
}
