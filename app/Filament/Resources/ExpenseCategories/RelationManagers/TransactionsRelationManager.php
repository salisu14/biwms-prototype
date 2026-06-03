<?php

namespace App\Filament\Resources\ExpenseCategories\RelationManagers;

use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $relatedResource = ExpenseCategoryResource::class;

    protected static ?string $title = 'Historical Ledger Entries';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('document_no')
                    ->disabled(),
                TextInput::make('amount_lcy')
                    ->label('Amount (LCY)')
                    ->numeric()
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_no')
            ->columns([
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('document_no')
                    ->label('Doc No.')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('amount_lcy')
                    ->label('Amount (LCY)')
                    ->money('NGN')
                    ->sortable()
                    ->alignment('right'),
                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor/Entity')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'posted' => 'success',
                        'reversed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'posted' => 'Posted',
                        'reversed' => 'Reversed',
                    ]),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
                //                    ->infolist(fn (Infolist $infolist) => self::getInfolist($infolist)),
            ])
            ->toolbarActions([]);
    }

    public static function getInfolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Transaction Detail')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('document_no')->label('Document No.'),
                            TextEntry::make('posting_date')->date(),
                            TextEntry::make('amount_lcy')->money('NGN')->label('Amount (LCY)'),
                            TextEntry::make('vendor.vendor_name')->label('Vendor'),
                            TextEntry::make('purchase_order_no')->label('PO Ref'),
                            TextEntry::make('expenseAccount.name')->label('G/L Account'),
                        ]),
                    ]),
                Section::make('Allocations')
                    ->schema([
                        RepeatableEntry::make('allocations')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('targetAccount.name')->label('Target G/L'),
                                    TextEntry::make('allocation_percentage')->suffix('%'),
                                    TextEntry::make('allocated_amount')->money('NGN'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
