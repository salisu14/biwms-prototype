<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceDevices;

use App\Filament\Resources\AttendanceDevices\Pages\CreateAttendanceDevice;
use App\Filament\Resources\AttendanceDevices\Pages\EditAttendanceDevice;
use App\Filament\Resources\AttendanceDevices\Pages\ListAttendanceDevices;
use App\Filament\Resources\AttendanceDevices\Pages\ViewAttendanceDevice;
use App\Filament\Resources\AttendanceDevices\Schemas\AttendanceDeviceForm;
use App\Filament\Resources\AttendanceDevices\Schemas\AttendanceDeviceInfolist;
use App\Filament\Resources\AttendanceDevices\Tables\AttendanceDevicesTable;
use App\Models\AttendanceDevice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttendanceDeviceResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'attendance_device';
    }

    protected static ?string $model = AttendanceDevice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return AttendanceDeviceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceDeviceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceDevicesTable::configure($table);
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
            'index' => ListAttendanceDevices::route('/'),
            'create' => CreateAttendanceDevice::route('/create'),
            'view' => ViewAttendanceDevice::route('/{record}'),
            'edit' => EditAttendanceDevice::route('/{record}/edit'),
        ];
    }
}
