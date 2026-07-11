<?php

declare(strict_types=1);

namespace App\Filament\Resources\OvertimeApprovals;

use App\Filament\Resources\OvertimeApprovals\Pages\CreateOvertimeApproval;
use App\Filament\Resources\OvertimeApprovals\Pages\EditOvertimeApproval;
use App\Filament\Resources\OvertimeApprovals\Pages\ListOvertimeApprovals;
use App\Filament\Resources\OvertimeApprovals\Pages\ViewOvertimeApproval;
use App\Filament\Resources\OvertimeApprovals\Schemas\OvertimeApprovalForm;
use App\Filament\Resources\OvertimeApprovals\Schemas\OvertimeApprovalInfolist;
use App\Filament\Resources\OvertimeApprovals\Tables\OvertimeApprovalsTable;
use App\Models\OvertimeApproval;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OvertimeApprovalResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'overtime_approval';
    }

    protected static ?string $model = OvertimeApproval::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Overtime';

    protected static ?int $navigationSort = 80;

    public static function form(Schema $schema): Schema
    {
        return OvertimeApprovalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OvertimeApprovalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OvertimeApprovalsTable::configure($table);
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
            'index' => ListOvertimeApprovals::route('/'),
            'create' => CreateOvertimeApproval::route('/create'),
            'view' => ViewOvertimeApproval::route('/{record}'),
            'edit' => EditOvertimeApproval::route('/{record}/edit'),
        ];
    }
}
