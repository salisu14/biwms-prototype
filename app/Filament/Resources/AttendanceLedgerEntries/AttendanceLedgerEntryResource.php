<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceLedgerEntries;

use App\Filament\Resources\AttendanceLedgerEntries\Pages\CreateAttendanceLedgerEntry;
use App\Filament\Resources\AttendanceLedgerEntries\Pages\EditAttendanceLedgerEntry;
use App\Filament\Resources\AttendanceLedgerEntries\Pages\ListAttendanceLedgerEntries;
use App\Filament\Resources\AttendanceLedgerEntries\Pages\ViewAttendanceLedgerEntry;
use App\Filament\Resources\AttendanceLedgerEntries\Schemas\AttendanceLedgerEntryForm;
use App\Filament\Resources\AttendanceLedgerEntries\Schemas\AttendanceLedgerEntryInfolist;
use App\Filament\Resources\AttendanceLedgerEntries\Tables\AttendanceLedgerEntriesTable;
use App\Models\AttendanceLedgerEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttendanceLedgerEntryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'attendance_ledger_entry';
    }

    protected static ?string $model = AttendanceLedgerEntry::class;

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return AttendanceLedgerEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceLedgerEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceLedgerEntriesTable::configure($table);
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
            'index' => ListAttendanceLedgerEntries::route('/'),
            'create' => CreateAttendanceLedgerEntry::route('/create'),
            'view' => ViewAttendanceLedgerEntry::route('/{record}'),
            'edit' => EditAttendanceLedgerEntry::route('/{record}/edit'),
        ];
    }
}
