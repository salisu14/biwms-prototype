<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewItems;

use App\Filament\Resources\AttendanceReviewItems\Pages\CreateAttendanceReviewItem;
use App\Filament\Resources\AttendanceReviewItems\Pages\EditAttendanceReviewItem;
use App\Filament\Resources\AttendanceReviewItems\Pages\ListAttendanceReviewItems;
use App\Filament\Resources\AttendanceReviewItems\Pages\ViewAttendanceReviewItem;
use App\Filament\Resources\AttendanceReviewItems\Schemas\AttendanceReviewItemForm;
use App\Filament\Resources\AttendanceReviewItems\Schemas\AttendanceReviewItemInfolist;
use App\Filament\Resources\AttendanceReviewItems\Tables\AttendanceReviewItemsTable;
use App\Models\AttendanceReviewItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttendanceReviewItemResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'attendance_review_item';
    }

    protected static ?string $model = AttendanceReviewItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Attendance Exceptions';

    public static function form(Schema $schema): Schema
    {
        return AttendanceReviewItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceReviewItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceReviewItemsTable::configure($table);
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
            'index' => ListAttendanceReviewItems::route('/'),
            'create' => CreateAttendanceReviewItem::route('/create'),
            'view' => ViewAttendanceReviewItem::route('/{record}'),
            'edit' => EditAttendanceReviewItem::route('/{record}/edit'),
        ];
    }
}
