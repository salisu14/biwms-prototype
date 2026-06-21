<?php

namespace App\Filament\Resources\MaintenanceContracts\Schemas;

use App\Enums\MaintenanceContractBillingCycle;
use App\Enums\MaintenanceContractStatus;
use App\Enums\MaintenanceContractType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contract Header')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('contract_no')
                                ->label('Contract No.')
                                ->required()
                                ->maxLength(50)
                                ->unique(ignoreRecord: true),
                            TextInput::make('description')
                                ->required()
                                ->maxLength(200)
                                ->columnSpan(2),
                            Select::make('contract_type')
                                ->options(collect(MaintenanceContractType::cases())->mapWithKeys(fn (MaintenanceContractType $case) => [
                                    $case->value => str($case->name)->replace('_', ' ')->title()->toString(),
                                ])->all())
                                ->required(),
                            Select::make('status')
                                ->options(collect(MaintenanceContractStatus::cases())->mapWithKeys(fn (MaintenanceContractStatus $case) => [
                                    $case->value => str($case->name)->replace('_', ' ')->title()->toString(),
                                ])->all())
                                ->default(MaintenanceContractStatus::DRAFT->value)
                                ->required(),
                            Select::make('vendor_id')
                                ->relationship('vendor', 'vendor_name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                    ]),
                Section::make('Dates & Billing')
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('start_date')->required(),
                            DatePicker::make('end_date')->required(),
                            DatePicker::make('renewal_date'),
                            Select::make('billing_cycle')
                                ->options(collect(MaintenanceContractBillingCycle::cases())->mapWithKeys(fn (MaintenanceContractBillingCycle $case) => [
                                    $case->value => str($case->name)->replace('_', ' ')->title()->toString(),
                                ])->all())
                                ->required(),
                            TextInput::make('contract_value')->numeric()->required(),
                            TextInput::make('billing_amount')->numeric()->required(),
                            TextInput::make('currency_code')->default('USD')->required()->maxLength(10),
                            Toggle::make('auto_renewal')->default(false),
                            TextInput::make('notice_period_days')->numeric()->default(30),
                        ]),
                    ]),
                Section::make('Accounting')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('expense_account_id')->relationship('expenseAccount', 'name')->searchable()->preload()->required(),
                            Select::make('prepaid_account_id')->relationship('prepaidAccount', 'name')->searchable()->preload(),
                            Select::make('accrual_account_id')->relationship('accrualAccount', 'name')->searchable()->preload(),
                        ]),
                    ]),
                Section::make('Scope')
                    ->schema([
                        Textarea::make('scope_of_work')->rows(3),
                        Textarea::make('special_terms')->rows(3),
                        Textarea::make('exclusions')->rows(3),
                    ]),
            ]);
    }
}
