<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollRules;

use App\Filament\Resources\AttendancePayrollRules\Pages\CreateAttendancePayrollRule;
use App\Filament\Resources\AttendancePayrollRules\Pages\EditAttendancePayrollRule;
use App\Filament\Resources\AttendancePayrollRules\Pages\ListAttendancePayrollRules;
use App\Filament\Resources\AttendancePayrollRules\Pages\ViewAttendancePayrollRule;
use App\Filament\Resources\AttendancePayrollRules\Schemas\AttendancePayrollRuleForm;
use App\Filament\Resources\AttendancePayrollRules\Schemas\AttendancePayrollRuleInfolist;
use App\Filament\Resources\AttendancePayrollRules\Tables\AttendancePayrollRulesTable;
use App\Models\AttendancePayrollRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttendancePayrollRuleResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'payroll';
    }

    public static function permissionResource(): string
    {
        return 'attendance_rule';
    }

    protected static ?string $model = AttendancePayrollRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Attendance Payroll Rules';

    public static function form(Schema $schema): Schema
    {
        return AttendancePayrollRuleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendancePayrollRuleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendancePayrollRulesTable::configure($table);
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
            'index' => ListAttendancePayrollRules::route('/'),
            'create' => CreateAttendancePayrollRule::route('/create'),
            'view' => ViewAttendancePayrollRule::route('/{record}'),
            'edit' => EditAttendancePayrollRule::route('/{record}/edit'),
        ];
    }
}
