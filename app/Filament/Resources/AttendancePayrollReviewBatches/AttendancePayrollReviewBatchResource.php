<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatches;

use App\Filament\Resources\AttendancePayrollReviewBatches\Pages\CreateAttendancePayrollReviewBatch;
use App\Filament\Resources\AttendancePayrollReviewBatches\Pages\EditAttendancePayrollReviewBatch;
use App\Filament\Resources\AttendancePayrollReviewBatches\Pages\ListAttendancePayrollReviewBatches;
use App\Filament\Resources\AttendancePayrollReviewBatches\Pages\ViewAttendancePayrollReviewBatch;
use App\Filament\Resources\AttendancePayrollReviewBatches\Schemas\AttendancePayrollReviewBatchForm;
use App\Filament\Resources\AttendancePayrollReviewBatches\Schemas\AttendancePayrollReviewBatchInfolist;
use App\Filament\Resources\AttendancePayrollReviewBatches\Tables\AttendancePayrollReviewBatchesTable;
use App\Models\AttendancePayrollReviewBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttendancePayrollReviewBatchResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'payroll';
    }

    public static function permissionResource(): string
    {
        return 'attendance_batch';
    }

    protected static ?string $model = AttendancePayrollReviewBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Attendance Payroll Batches';

    public static function form(Schema $schema): Schema
    {
        return AttendancePayrollReviewBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendancePayrollReviewBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendancePayrollReviewBatchesTable::configure($table);
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
            'index' => ListAttendancePayrollReviewBatches::route('/'),
            'create' => CreateAttendancePayrollReviewBatch::route('/create'),
            'view' => ViewAttendancePayrollReviewBatch::route('/{record}'),
            'edit' => EditAttendancePayrollReviewBatch::route('/{record}/edit'),
        ];
    }
}
