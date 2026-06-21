<?php

namespace App\Filament\Resources\ChartOfAccounts\Schemas;

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\DepreciationCalculationMethod;
use App\Enums\DepreciationMethod;
use App\Models\ChartOfAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ChartOfAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('G/L Account Card')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('account_number')
                                        ->label('Account No.')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->disabled(fn(?ChartOfAccount $record) => $record !== null)
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                    TextInput::make('name')
                                        ->required()
                                        ->columnSpan(2),
                                ]),
                                Grid::make(3)->schema([
                                    Select::make('account_category')
                                        ->options(AccountCategory::class)
                                        ->required()
                                        ->live()
                                        ->native(false),
                                    Select::make('structural_type')
                                        ->label('Account Type')
                                        ->options(AccountStructuralType::class)
                                        ->default(AccountStructuralType::POSTING)
                                        ->required()
                                        ->native(false),
                                    TextInput::make('search_name')
                                        ->placeholder('Defaults to Name'),
                                ]),
                                Section::make('Posting Restrictions')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Toggle::make('direct_posting')
                                                ->helperText('Allows manual journal entry posting.')
                                                ->default(true),
                                            Toggle::make('blocked')
                                                ->onColor('danger'),
                                            Select::make('parent_account_id')
                                                ->relationship('parentAccount', 'name')
                                                ->getOptionLabelFromRecordUsing(fn($record) => "{$record->account_number} - {$record->name}")
                                                ->searchable()
                                                ->preload(),
                                        ]),
                                    ])->compact(),
                            ]),

                        Tabs\Tab::make('Posting Groups')
                            ->icon('heroicon-m-tag')
                            ->schema([
                                Section::make('General Posting Setup')
                                    ->description('Defines how the system routes transactions for this account.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('gen_bus_posting_group_id')
                                                ->label('Gen. Bus. Posting Group')
                                                ->relationship('genBusPostingGroup', 'code')
                                                ->searchable()
                                                ->preload(),
                                            Select::make('gen_prod_posting_group_id')
                                                ->label('Gen. Prod. Posting Group')
                                                ->relationship('genProdPostingGroup', 'code')
                                                ->searchable()
                                                ->preload(),
                                        ]),
                                    ]),
                                Section::make('Tax (VAT) Posting Setup')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('vat_bus_posting_group_id')
                                                ->label('VAT Bus. Posting Group')
                                                ->relationship('vatBusPostingGroup', 'code')
                                                ->searchable()
                                                ->preload(),
                                            Select::make('vat_prod_posting_group_id')
                                                ->label('VAT Prod. Posting Group')
                                                ->relationship('vatProdPostingGroup', 'code')
                                                ->searchable()
                                                ->preload(),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Reporting & Layout')
                            ->icon('heroicon-m-document-chart-bar')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('totaling')
                                        ->placeholder('e.g., 1100..1200 or 1100|1300')
                                        ->helperText('Used for Heading and Total accounts.'),
                                    TextInput::make('indentation')
                                        ->numeric()
                                        ->default(0),
                                ]),
                                Section::make('Format Settings')
                                    ->columns(4)
                                    ->schema([
                                        Toggle::make('bold'),
                                        Toggle::make('italic'),
                                        Toggle::make('underline'),
                                        Toggle::make('show_opposite_sign'),
                                        Toggle::make('new_page'),
                                        TextInput::make('no_of_blank_lines')->numeric()->default(0),
                                    ]),
                            ]),

                        Tabs\Tab::make('Consolidation')
                            ->icon('heroicon-m-globe-alt')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('consol_debit_acc'),
                                    TextInput::make('consol_credit_acc'),
                                    Select::make('consol_translation_method')
                                        ->options([
                                            'average' => 'Average Rate',
                                            'closing' => 'Closing Rate',
                                            'historical' => 'Historical Rate',
                                        ]),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
