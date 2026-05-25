<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GlEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'glEntries';

    protected static ?string $title = 'Accounting G/L Entries';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('posting_date')->date()->sortable(),
                TextColumn::make('document_number')->searchable(),
                TextColumn::make('chartOfAccount.account_number')->label('Acc No')->sortable(),
                TextColumn::make('chartOfAccount.name')->label('Account Name'),
                TextColumn::make('description')->searchable(),
                TextColumn::make('debit_amount')
                    ->label('Debit')
                    ->money('USD')
                    ->color('success')
                    ->summarize(Sum::make()->money('USD')),
                TextColumn::make('credit_amount')
                    ->label('Credit')
                    ->money('USD')
                    ->color('danger')
                    ->summarize(Sum::make()->money('USD')),
                TextColumn::make('transaction_number')->label('Txn')->sortable()->toggleable(),
            ]);
    }
}
