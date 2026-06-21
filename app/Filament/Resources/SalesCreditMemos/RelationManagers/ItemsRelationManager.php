<?php

namespace App\Filament\Resources\SalesCreditMemos\RelationManagers;

use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use App\Models\Item;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $relatedResource = SalesCreditMemoResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('item_id')
                    ->relationship('item', 'description')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn (Set $set, $state) => $set('unit_price', Item::find($state)?->unit_price ?? 0))
                    ->columnSpan(2),

                TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateTotals($get, $set)),

                TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('₦')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateTotals($get, $set)),

                Grid::make(3)
                    ->schema([
                        TextInput::make('line_discount_percent')
                            ->label('Disc %')
                            ->numeric()
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateTotals($get, $set)),

                        TextInput::make('vat_percent')
                            ->label('VAT %')
                            ->numeric()
                            ->default(7.5)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateTotals($get, $set)),

                        TextInput::make('unit_of_measure_code')
                            ->label('UOM')
                            ->placeholder('PCS'),
                    ]),

                Section::make('Totals')
                    ->columns(3)
                    ->compact()
                    ->schema([
                        Placeholder::make('amount_placeholder')
                            ->label('Net Amount')
                            ->content(fn (Get $get) => number_format((float) $get('amount'), 2)),

                        Placeholder::make('vat_amount_placeholder')
                            ->label('VAT Amount')
                            ->content(fn (Get $get) => number_format((float) $get('vat_amount'), 2)),

                        Placeholder::make('total_placeholder')
                            ->label('Total (Gross)')
                            ->extraAttributes(['class' => 'font-bold text-primary-600'])
                            ->content(fn (Get $get) => number_format((float) $get('amount_including_vat'), 2)),
                    ]),

                // Hidden fields to store calculated data
                Hidden::make('amount'),
                Hidden::make('vat_amount'),
                Hidden::make('amount_including_vat'),
                Hidden::make('line_discount_amount'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('line_no')
            ->columns([
                TextColumn::make('item.name')
                    ->label('Item')
                    ->description(fn ($record) => "UOM: {$record->unit_of_measure_code}"),

                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('unit_price')
                    ->money('NGN'),

                TextColumn::make('line_discount_amount')
                    ->label('Discount')
                    ->money('NGN')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('amount')
                    ->label('Net')
                    ->money('NGN'),

                TextColumn::make('vat_amount')
                    ->label('VAT')
                    ->money('NGN'),

                TextColumn::make('amount_including_vat')
                    ->label('Gross')
                    ->money('NGN')
                    ->weight('bold'),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['line_no'] = self::getNextLineNo();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Logic to calculate line totals based on UI input
     */
    public static function calculateTotals(Get $get, Set $set): void
    {
        $qty = (float) $get('quantity') ?? 0;
        $price = (float) $get('unit_price') ?? 0;
        $discPercent = (float) $get('line_discount_percent') ?? 0;
        $vatPercent = (float) $get('vat_percent') ?? 0;

        $baseAmount = $qty * $price;
        $discountAmount = round($baseAmount * ($discPercent / 100), 2);
        $netAmount = $baseAmount - $discountAmount;
        $vatAmount = round($netAmount * ($vatPercent / 100), 2);
        $total = $netAmount + $vatAmount;

        $set('line_discount_amount', $discountAmount);
        $set('amount', $netAmount);
        $set('vat_amount', $vatAmount);
        $set('amount_including_vat', $total);
    }

    protected static function getNextLineNo(): int
    {
        // Simple logic to increment line numbers within the current memo
        return 10000; // You can implement specific logic here if needed
    }
}
