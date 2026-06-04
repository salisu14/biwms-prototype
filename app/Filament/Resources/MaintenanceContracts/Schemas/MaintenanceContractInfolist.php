<?php

namespace App\Filament\Resources\MaintenanceContracts\Schemas;

use App\Filament\Resources\ChartOfAccounts\ChartOfAccountResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\MaintenanceContract;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scope')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contract_no')
                            ->label('Contract No.'),
                        TextEntry::make('description')
                            ->label('Description'),
                        TextEntry::make('vendor')
                            ->label('Vendor')
                            ->state(function (MaintenanceContract $record): string {
                                return $record->vendor
                                    ? "{$record->vendor->vendor_name} - {$record->vendor->vendor_code}"
                                    : '—';
                            })
                            ->url(fn (MaintenanceContract $record): ?string => $record->vendor
                                ? VendorResource::getUrl('view', ['record' => $record->vendor])
                                : null),
                        TextEntry::make('responsible_employee')
                            ->label('Responsible Employee')
                            ->state(function (MaintenanceContract $record): string {
                                return $record->responsibleEmployee
                                    ? "{$record->responsibleEmployee->employee_number} - {$record->responsibleEmployee->full_name}"
                                    : '—';
                            })
                            ->url(fn (MaintenanceContract $record): ?string => $record->responsibleEmployee
                                ? EmployeeResource::getUrl('view', ['record' => $record->responsibleEmployee])
                                : null),
                    ]),

                Section::make('Billing & Dates')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('contract_type')
                            ->badge()
                            ->label('Type')
                            ->state(fn (MaintenanceContract $record): string => $record->contract_type?->value ?? '—'),
                        TextEntry::make('status')
                            ->badge()
                            ->label('Status')
                            ->state(fn (MaintenanceContract $record): string => $record->status?->value ?? '—'),
                        TextEntry::make('billing_cycle')
                            ->badge()
                            ->label('Billing Cycle')
                            ->state(fn (MaintenanceContract $record): string => $record->billing_cycle?->value ?? '—'),
                        TextEntry::make('start_date')->date()->label('Start Date'),
                        TextEntry::make('end_date')->date()->label('End Date'),
                        TextEntry::make('renewal_date')->date()->label('Renewal Date'),
                        TextEntry::make('contract_value')
                            ->money(fn (MaintenanceContract $record) => $record->currency_code ?: 'NGN')
                            ->label('Contract Value'),
                        TextEntry::make('billing_amount')
                            ->money(fn (MaintenanceContract $record) => $record->currency_code ?: 'NGN')
                            ->label('Billing Amount'),
                        TextEntry::make('notice_period_days')->label('Notice Period (Days)'),
                    ]),

                Section::make('Accounts')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('expense_account')
                            ->label('Expense Account')
                            ->state(fn (MaintenanceContract $record): string => $record->expenseAccount
                                ? "{$record->expenseAccount->account_number} - {$record->expenseAccount->name}"
                                : '—')
                            ->url(fn (MaintenanceContract $record): ?string => $record->expenseAccount
                                ? ChartOfAccountResource::getUrl('view', ['record' => $record->expenseAccount])
                                : null),
                        TextEntry::make('prepaid_account')
                            ->label('Prepaid Account')
                            ->state(fn (MaintenanceContract $record): string => $record->prepaidAccount
                                ? "{$record->prepaidAccount->account_number} - {$record->prepaidAccount->name}"
                                : '—')
                            ->url(fn (MaintenanceContract $record): ?string => $record->prepaidAccount
                                ? ChartOfAccountResource::getUrl('view', ['record' => $record->prepaidAccount])
                                : null),
                        TextEntry::make('accrual_account')
                            ->label('Accrual Account')
                            ->state(fn (MaintenanceContract $record): string => $record->accrualAccount
                                ? "{$record->accrualAccount->account_number} - {$record->accrualAccount->name}"
                                : '—')
                            ->url(fn (MaintenanceContract $record): ?string => $record->accrualAccount
                                ? ChartOfAccountResource::getUrl('view', ['record' => $record->accrualAccount])
                                : null),
                    ]),

                Section::make('Coverage')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('auto_renewal')->boolean()->label('Auto Renewal'),
                        TextEntry::make('auto_renewal_period_months')->label('Auto Renewal Period (Months)'),
                        TextEntry::make('scope_of_work')->columnSpanFull()->label('Scope of Work'),
                        TextEntry::make('special_terms')->columnSpanFull()->label('Special Terms'),
                        TextEntry::make('exclusions')->columnSpanFull()->label('Exclusions'),
                    ]),

                Section::make('Audit')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}
