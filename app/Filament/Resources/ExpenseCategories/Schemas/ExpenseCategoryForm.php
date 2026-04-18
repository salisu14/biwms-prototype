<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use App\Enums\AccountType;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('Classification')
                        ->description('Define the financial bucket for this category.')
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('account_type')
                                    ->label('Account Class')
                                    ->options(AccountType::class)
                                    ->required()
                                    ->native(false),
                                Select::make('category_type')
                                    ->label('Type')
                                    ->options([
                                        'expense' => 'Operating Expense',
                                        'revenue' => 'Revenue / Income',
                                        'cogs' => 'Cost of Goods Sold',
                                    ])
                                    ->required()
                                    ->live()
                                    ->native(false),
                            ]),
                            TextInput::make('category_code')
                                ->label('Internal Code')
                                ->required()
                                ->placeholder('e.g., TRAVEL_OVERHEAD')
                                ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                            TextInput::make('description')
                                ->label('Display Name')
                                ->required()
                                ->placeholder('e.g., Marketing & Advertising'),
                        ])->columnSpan(2),

                    Section::make('Management Status')
                        ->schema([
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->onColor('success'),
                            Toggle::make('is_controllable')
                                ->label('Controllable')
                                ->helperText('Can department managers adjust this?'),
                            Toggle::make('is_direct')
                                ->label('Direct Cost')
                                ->helperText('Attributable to specific production.'),
                            Toggle::make('is_variable')
                                ->label('Variable')
                                ->helperText('Fluctuates with production volume.'),
                        ])->columnSpan(1),
                ]),

                Section::make('Accounting & Posting Rules')
                    ->description('Configuration for G/L integration.')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('expense_account_id')
                                ->label('Primary G/L Account')
                                ->relationship('expenseAccount', 'name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->name}")
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('gen_prod_posting_group_id')
                                ->label('Gen. Prod. Posting Group')
                                ->relationship('generalProductPostingGroup', 'code')
                                ->searchable()
                                ->preload(),
                            Select::make('vat_prod_posting_group_id')
                                ->label('VAT Prod. Posting Group')
                                ->relationship('vatProductPostingGroup', 'code')
                                ->searchable()
                                ->preload(),
                        ]),
                        Grid::make(2)->schema([
                            Select::make('contra_account_id')
                                ->label('Contra / Offset Account')
                                ->relationship('contraAccount', 'name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->name}")
                                ->searchable()
                                ->preload(),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('default_dimension_1')
                                ->label('Global Dimension 1'),
                            TextInput::make('default_dimension_2')
                                ->label('Global Dimension 2'),
                        ]),
                        KeyValue::make('posting_rules')
                            ->label('Specific Posting Overrides')
                            ->keyLabel('Condition')
                            ->valueLabel('Override Account'),
                    ]),

                Section::make('Additional Metadata')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('category_id')
                                ->label('Related Product Category')
                                ->relationship('category', 'category_name')
                                ->searchable(),
                        ]),
                        Textarea::make('notes')
                            ->label('Administrative Notes')
                            ->rows(3),
                    ]),
            ]);
    }
}
