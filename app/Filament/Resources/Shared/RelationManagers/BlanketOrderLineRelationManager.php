<?php

namespace App\Filament\Resources\Shared\RelationManagers;

use App\Models\BlanketOrder;
use App\Models\ChartOfAccount;
use App\Models\Item;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlanketOrderLineRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Lines';

    public function form(Schema $schema): Schema
    {
        /** @var BlanketOrder $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();
        $isSales = $ownerRecord->order_type === 'Sales';

        return $schema
            ->schema([
                Grid::make(2)->schema([
                    Select::make('type')
                        ->options([
                            'ITEM' => 'Item',
                            'GL_ACCOUNT' => 'G/L Account',
                        ])
                        ->default('ITEM')
                        ->required(),
                    Select::make('no')
                        ->label('No.')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(function (Get $get) use ($isSales) {
                            $type = $get('type');
                            if ($type === 'GL_ACCOUNT') {
                                return ChartOfAccount::where('blocked', false)
                                    ->where('direct_posting', true)
                                    ->pluck('account_number', 'account_number');
                            }

                            if ($type === 'ITEM') {
                                $query = Item::where('blocked', false);
                                if ($isSales) {
                                    $query->finishedGoods();
                                } else {
                                    $query->rawMaterials();
                                }

                                return $query->pluck('item_code', 'item_code');
                            }

                            return [];
                        })
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            if (! $state) {
                                return;
                            }
                            $type = $get('type');
                            if ($type === 'ITEM') {
                                $item = Item::where('item_code', $state)->first();
                                if ($item) {
                                    $set('description', $item->description);
                                    $set('unit_of_measure', $item->base_unit_of_measure);
                                    if ($get('order_type') === 'Sales') {
                                        $set('unit_price', $item->unit_price);
                                    } else {
                                        $set('direct_unit_cost', $item->current_standard_cost);
                                    }
                                }
                            } elseif ($type === 'GL_ACCOUNT') {
                                $account = ChartOfAccount::where('account_number', $state)->first();
                                if ($account) {
                                    $set('description', $account->name);
                                }
                            }
                        }),
                ]),

                TextInput::make('description')
                    ->required()
                    ->columnSpanFull(),

                Grid::make(3)->schema([
                    TextInput::make('quantity')
                        ->numeric()
                        ->default(1)
                        ->required(),

                    TextInput::make('unit_of_measure'),

                    TextInput::make($isSales ? 'unit_price' : 'direct_unit_cost')
                        ->label($isSales ? 'Unit Price' : 'Direct Unit Cost')
                        ->numeric()
                        ->prefix('$')
                        ->required(),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var BlanketOrder $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();
        $isSales = $ownerRecord->order_type === 'Sales';

        return $table
            ->columns([
                TextColumn::make('line_number')->label('#'),
                TextColumn::make('type'),
                TextColumn::make('no'),
                TextColumn::make('description'),
                TextColumn::make('quantity')->numeric(),

                TextColumn::make($isSales ? 'quantity_shipped' : 'quantity_received')
                    ->label($isSales ? 'Shipped' : 'Rec.')
                    ->numeric(),

                TextColumn::make($isSales ? 'unit_price' : 'direct_unit_cost')
                    ->label($isSales ? 'Unit Price' : 'Direct Unit Cost')
                    ->money('USD'),

                TextColumn::make('line_amount')
                    ->money('USD'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data, RelationManager $livewire): array {
                        $maxLine = $livewire->getOwnerRecord()->lines()->max('line_number') ?? 0;
                        $data['line_number'] = $maxLine + 10000;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
