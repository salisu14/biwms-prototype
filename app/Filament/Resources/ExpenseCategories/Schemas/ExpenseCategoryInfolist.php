<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ExpenseCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('account_type')
                    ->badge(),
                TextEntry::make('category_code'),
                TextEntry::make('category_type'),
                TextEntry::make('description'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('is_direct')
                    ->boolean(),
                IconEntry::make('is_variable')
                    ->boolean(),
                IconEntry::make('is_controllable')
                    ->boolean(),
                TextEntry::make('productCategory.id')
                    ->label('Product category')
                    ->placeholder('-'),
                TextEntry::make('expenseAccount.name')
                    ->label('Expense account')
                    ->placeholder('-'),
                TextEntry::make('contraAccount.name')
                    ->label('Contra account')
                    ->placeholder('-'),
                TextEntry::make('default_dimension_1')
                    ->placeholder('-'),
                TextEntry::make('default_dimension_2')
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
