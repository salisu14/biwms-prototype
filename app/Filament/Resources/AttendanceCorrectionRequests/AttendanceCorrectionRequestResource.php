<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceCorrectionRequests;

use App\Filament\Resources\AttendanceCorrectionRequests\Pages\CreateAttendanceCorrectionRequest;
use App\Filament\Resources\AttendanceCorrectionRequests\Pages\EditAttendanceCorrectionRequest;
use App\Filament\Resources\AttendanceCorrectionRequests\Pages\ListAttendanceCorrectionRequests;
use App\Filament\Resources\AttendanceCorrectionRequests\Pages\ViewAttendanceCorrectionRequest;
use App\Filament\Resources\AttendanceCorrectionRequests\Schemas\AttendanceCorrectionRequestForm;
use App\Filament\Resources\AttendanceCorrectionRequests\Schemas\AttendanceCorrectionRequestInfolist;
use App\Filament\Resources\AttendanceCorrectionRequests\Tables\AttendanceCorrectionRequestsTable;
use App\Models\AttendanceCorrectionRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttendanceCorrectionRequestResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'attendance_correction';
    }

    protected static ?string $model = AttendanceCorrectionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Attendance Corrections';

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return AttendanceCorrectionRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceCorrectionRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceCorrectionRequestsTable::configure($table);
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
            'index' => ListAttendanceCorrectionRequests::route('/'),
            'create' => CreateAttendanceCorrectionRequest::route('/create'),
            'view' => ViewAttendanceCorrectionRequest::route('/{record}'),
            'edit' => EditAttendanceCorrectionRequest::route('/{record}/edit'),
        ];
    }
}
