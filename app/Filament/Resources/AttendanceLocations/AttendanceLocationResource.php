<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceLocations;

use App\Filament\Resources\AttendanceLocations\Pages\CreateAttendanceLocation;
use App\Filament\Resources\AttendanceLocations\Pages\EditAttendanceLocation;
use App\Filament\Resources\AttendanceLocations\Pages\ListAttendanceLocations;
use App\Filament\Resources\AttendanceLocations\Pages\ViewAttendanceLocation;
use App\Filament\Resources\AttendanceLocations\Schemas\AttendanceLocationForm;
use App\Filament\Resources\AttendanceLocations\Schemas\AttendanceLocationInfolist;
use App\Filament\Resources\AttendanceLocations\Tables\AttendanceLocationsTable;
use App\Models\AttendanceLocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttendanceLocationResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'attendance_location';
    }

    protected static ?string $model = AttendanceLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return AttendanceLocationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceLocationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceLocationsTable::configure($table);
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
            'index' => ListAttendanceLocations::route('/'),
            'create' => CreateAttendanceLocation::route('/create'),
            'view' => ViewAttendanceLocation::route('/{record}'),
            'edit' => EditAttendanceLocation::route('/{record}/edit'),
        ];
    }
}
