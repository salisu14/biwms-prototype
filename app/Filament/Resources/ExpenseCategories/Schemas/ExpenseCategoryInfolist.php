<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->description('Primary classification and naming for this expense category.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('category_code')
                            ->label('Code')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('description')
                            ->label('Description'),

                        TextEntry::make('account_type')
                            ->label('Account Type')
                            ->badge(),

                        TextEntry::make('category_type')
                            ->label('Category Type')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('category.name')
                            ->label('Parent Category')
                            ->placeholder('No parent category assigned'),

                        IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean(),
                    ]),

                Section::make('Attributes & Classification')
                    ->description('Flags indicating how this category behaves in financial reporting.')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('is_direct')
                            ->label('Direct Expense')
                            ->boolean(),

                        IconEntry::make('is_variable')
                            ->label('Variable Cost')
                            ->boolean(),

                        IconEntry::make('is_controllable')
                            ->label('Controllable')
                            ->boolean(),
                    ]),

                Section::make('Accounting & Mapping')
                    ->description('Links to the General Ledger and dimension defaults.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('expenseAccount.name')
                            ->label('Expense G/L Account')
                            ->icon('heroicon-m-building-library')
                            ->placeholder('-'),

                        TextEntry::make('contraAccount.name')
                            ->label('Contra G/L Account')
                            ->icon('heroicon-m-arrows-right-left')
                            ->placeholder('-'),

                        TextEntry::make('default_dimension_1')
                            ->label('Primary Dimension')
                            ->placeholder('None'),

                        TextEntry::make('default_dimension_2')
                            ->label('Secondary Dimension')
                            ->placeholder('None'),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Internal Notes')
                            ->markdown()
                            ->placeholder('No notes recorded for this category.'),

                        TextEntry::make('created_at')
                            ->label('Registered On')
                            ->dateTime()
                            ->size('sm')
                            ->color('gray'),
                    ]),
            ]);
    }
}
