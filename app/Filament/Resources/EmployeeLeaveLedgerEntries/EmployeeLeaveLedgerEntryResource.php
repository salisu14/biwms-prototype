<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeLeaveLedgerEntries;

use App\Filament\Resources\EmployeeLeaveLedgerEntries\Pages\ListEmployeeLeaveLedgerEntries;
use App\Models\EmployeeLeaveLedgerEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeLeaveLedgerEntryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'leave_ledger';
    }

    protected static ?string $model = EmployeeLeaveLedgerEntry::class;

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?string $navigationLabel = 'Leave Ledger';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('posting_date', 'desc')
            ->columns([
                TextColumn::make('posting_date')->date()->sortable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('leaveType.name')->label('Leave Type')->searchable(),
                TextColumn::make('leave_year')->sortable(),
                TextColumn::make('entry_type')->badge(),
                TextColumn::make('quantity')->numeric()->sortable(),
                TextColumn::make('description')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('entry_type')
                    ->options([
                        EmployeeLeaveLedgerEntry::TYPE_OPENING => 'Opening',
                        EmployeeLeaveLedgerEntry::TYPE_ENTITLEMENT => 'Entitlement',
                        EmployeeLeaveLedgerEntry::TYPE_ACCRUAL => 'Accrual',
                        EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE => 'Approved Leave',
                        EmployeeLeaveLedgerEntry::TYPE_REVERSAL => 'Reversal',
                        EmployeeLeaveLedgerEntry::TYPE_CARRY_FORWARD => 'Carry Forward',
                        EmployeeLeaveLedgerEntry::TYPE_EXPIRY => 'Expiry',
                        EmployeeLeaveLedgerEntry::TYPE_ADJUSTMENT => 'Adjustment',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeLeaveLedgerEntries::route('/'),
        ];
    }
}
