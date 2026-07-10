<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeePayslipHistories;

use App\Filament\Resources\EmployeePayslipHistories\Pages\ListEmployeePayslipHistories;
use App\Models\EmployeePayslipHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeePayslipHistoryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_payslip_history';
    }

    protected static ?string $model = EmployeePayslipHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Payslip History';

    protected static ?int $navigationSort = 26;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('event')->badge()->searchable(),
                TextColumn::make('payslip.payslip_number')->label('Payslip No.')->searchable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('actor.name')->label('Actor')->toggleable(),
                TextColumn::make('description')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'generated' => 'Generated',
                        'previewed' => 'Previewed',
                        'downloaded' => 'Downloaded',
                        'printed' => 'Printed',
                        'emailed' => 'Emailed',
                        'revoked' => 'Revoked',
                        'regenerated' => 'Regenerated',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeePayslipHistories::route('/'),
        ];
    }
}
