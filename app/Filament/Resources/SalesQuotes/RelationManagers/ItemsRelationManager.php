<?php

namespace App\Filament\Resources\SalesQuotes\RelationManagers;

use App\Filament\Resources\SalesQuotes\SalesQuoteResource;
use App\Models\Item;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $relatedResource = SalesQuoteResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Select::make('item_id')
                            ->label('Item Selection')
                            ->relationship('item', 'description')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive() // Using live() is cleaner than reactive() in Filament v3
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $item = Item::find($get('item_id'));

                                if (!$item) return;

                                $set('unit_price', $item->unit_price);

                                $qty = (float) ($get('quantity') ?? 1);
                                $discount = (float) ($get('discount') ?? 0);

                                $set('line_total', ($qty * $item->unit_price) - $discount);
                            })
                            ->columnSpan(2),

                        TextInput::make('quantity')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true),

                        TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->numeric()
                            ->prefix('₦') // Specific for NGN
                            ->required()
                            ->live(onBlur: true),

                        TextInput::make('discount')
                            ->label('Discount Amount')
                            ->numeric()
                            ->default(0)
                            ->prefix('₦')
                            ->live(onBlur: true),

                        Placeholder::make('line_total_display')
                            ->label('Calculated Total')
                            ->extraAttributes(['class' => 'font-bold text-primary-600'])
                            ->content(function (Get $get) {
                                $qty = (float) ($get('quantity') ?? 0);
                                $price = (float) ($get('unit_price') ?? 0);
                                $discount = (float) ($get('discount') ?? 0);

                                $total = ($qty * $price) - $discount;
                                return '₦ ' . number_format(max(0, $total), 2);
                            }),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item.description')
            ->columns([
                TextColumn::make('item.description')
                    ->label('Product/Service')
                    ->description(fn ($record) => $record->item?->sku ?? '')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('unit_price')
                    ->label('Price')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('discount')
                    ->money('NGN')
                    ->color('danger')
                    ->description('Direct deduction'),

                TextColumn::make('line_total')
                    ->label('Total')
                    ->money('NGN')
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Subtotal')->money('NGN')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Item')
                    ->icon('heroicon-m-plus')
                    ->modalHeading('Add New Line Item'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->emptyStateHeading('No items found')
            ->emptyStateDescription('Start building the quote by adding items.');
    }
}
