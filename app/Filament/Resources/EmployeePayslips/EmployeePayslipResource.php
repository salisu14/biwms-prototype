<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeePayslips;

use App\Filament\Resources\EmployeePayslips\Pages\ListEmployeePayslips;
use App\Filament\Resources\EmployeePayslips\Pages\ViewEmployeePayslip;
use App\Models\EmployeePayslip;
use App\Services\Hr\EmployeePayslipService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeePayslipResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_payslip';
    }

    protected static ?string $model = EmployeePayslip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Employee Payslips';

    protected static ?int $navigationSort = 25;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee.department', 'payrollPeriod', 'payrollDocument']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payslip')
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 4,
                ])
                ->schema([
                    TextEntry::make('payslip_number')->label('Payslip No.'),
                    TextEntry::make('employee_name')->label('Employee'),
                    TextEntry::make('employee_number')->label('Employee No.'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('payrollDocument.document_number')->label('Payroll Run'),
                    TextEntry::make('payment_date')->date(),
                    TextEntry::make('gross_earnings')->money(fn (EmployeePayslip $record): string => $record->currency_code),
                    TextEntry::make('total_deductions')->money(fn (EmployeePayslip $record): string => $record->currency_code),
                    TextEntry::make('net_pay')->money(fn (EmployeePayslip $record): string => $record->currency_code),
                    TextEntry::make('download_count')->numeric(),
                    TextEntry::make('printed_at')->dateTime(),
                    TextEntry::make('generated_at')->dateTime(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('generated_at', 'desc')
            ->columns([
                TextColumn::make('payslip_number')->label('Payslip No.')->searchable()->sortable(),
                TextColumn::make('employee_number')->label('Emp No.')->searchable()->sortable(),
                TextColumn::make('employee_name')->label('Employee')->searchable()->sortable(),
                TextColumn::make('department_name')->label('Department')->toggleable(),
                TextColumn::make('payrollDocument.document_number')->label('Payroll Run')->searchable()->toggleable(),
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('net_pay')->money(fn (EmployeePayslip $record): string => $record->currency_code)->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        EmployeePayslip::STATUS_GENERATED, EmployeePayslip::STATUS_ISSUED => 'success',
                        EmployeePayslip::STATUS_DRAFT => 'gray',
                        EmployeePayslip::STATUS_REVOKED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('download_count')->numeric()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        EmployeePayslip::STATUS_DRAFT => 'Draft',
                        EmployeePayslip::STATUS_GENERATED => 'Generated',
                        EmployeePayslip::STATUS_ISSUED => 'Issued',
                        EmployeePayslip::STATUS_REVOKED => 'Revoked',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('preview')
                        ->icon('heroicon-o-eye')
                        ->url(fn (EmployeePayslip $record): string => route('employee-payslips.preview', $record))
                        ->openUrlInNewTab(),
                    Action::make('print')
                        ->icon('heroicon-o-printer')
                        ->url(fn (EmployeePayslip $record): string => route('employee-payslips.print', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (EmployeePayslip $record): bool => auth()->user()?->can('print', $record) === true),
                    Action::make('download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (EmployeePayslip $record): string => route('employee-payslips.download', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (EmployeePayslip $record): bool => auth()->user()?->can('download', $record) === true),
                    SensitiveActionPasswordConfirmation::protect(
                        Action::make('regenerate')
                            ->icon('heroicon-o-arrow-path')
                            ->color('warning')
                            ->visible(fn (EmployeePayslip $record): bool => auth()->user()?->can('regenerate', $record) === true)
                            ->action(function (EmployeePayslip $record): void {
                                app(EmployeePayslipService::class)->regenerate($record);
                            })
                    ),
                    SensitiveActionPasswordConfirmation::protect(
                        Action::make('revoke')
                            ->icon('heroicon-o-no-symbol')
                            ->color('danger')
                            ->form([
                                Textarea::make('reason')->label('Reason')->required()->maxLength(500),
                            ])
                            ->visible(fn (EmployeePayslip $record): bool => ! $record->isRevoked() && auth()->user()?->can('revoke', $record) === true)
                            ->action(function (EmployeePayslip $record, array $data): void {
                                app(EmployeePayslipService::class)->revoke($record, $data['reason'] ?? null);
                            })
                    ),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('downloadPayslips')
                        ->label('Bulk Download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn (): bool => auth()->user()?->can('hr.employee_payslip.download') === true)
                        ->url(fn (Collection $records): string => route('employee-payslips.bulk-download', [
                            'ids' => $records->pluck('id')->implode(','),
                        ]))
                        ->openUrlInNewTab(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeePayslips::route('/'),
            'view' => ViewEmployeePayslip::route('/{record}'),
        ];
    }
}
