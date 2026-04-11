<?php

namespace App\Filament\Resources\Currencies\RelationManagers;

use App\Filament\Resources\Currencies\CurrencyResource;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BuffersRelationManager extends RelationManager
{
    protected static string $relationship = 'buffers';

    protected static ?string $relatedResource = CurrencyResource::class;

    protected static ?string $title = 'Revaluation Buffers';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('buffer_type')
                        ->disabled(),
                    TextInput::make('posting_date')
                        ->disabled(),
                ]),
                Section::make('Valuation Details')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('remaining_amount_fcy')
                                ->label('Remaining (FCY)')
                                ->numeric()
                                ->disabled(),
                            TextInput::make('remaining_amount_lcy')
                                ->label('Remaining (LCY)')
                                ->numeric()
                                ->disabled(),
                            TextInput::make('original_exch_rate')
                                ->label('Original Rate')
                                ->numeric()
                                ->disabled(),
                            TextInput::make('current_exch_rate')
                                ->label('Latest Rate')
                                ->numeric()
                                ->disabled(),
                        ]),
                    ]),
                Toggle::make('adjusted')
                    ->label('Already Adjusted in G/L')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('buffer_type')
            ->columns([
                TextColumn::make('buffer_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'receivable' => 'success',
                        'payable' => 'danger',
                        'bank' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                // Polymorphic display logic
                TextColumn::make('entity_label')
                    ->label('Entity')
                    ->state(function (Model $record): string {
                        $entity = $record->entity;
                        if (! $entity) {
                            return 'Unknown';
                        }

                        // Adapt these attributes to match your Vendor/Customer/Bank models
                        return match ($record->entity_type) {
                            'App\Models\Vendor' => 'Vendor: '.($entity->vendor_name ?? $entity->vendor_code),
                            'App\Models\Customer' => 'Customer: '.($entity->name ?? $entity->customer_code),
                            'App\Models\BankAccount' => 'Bank: '.($entity->bank_name ?? $entity->account_no),
                            default => 'Other: '.$record->entity_id,
                        };
                    })
                    ->searchable(query: function ($query, string $search) {
                        // Complex polymorphic search usually requires specific join logic or
                        // searching only the IDs/Types. For simplicity, we search the types.
                        return $query->where('entity_type', 'like', "%{$search}%");
                    }),

                TextColumn::make('remaining_amount_fcy')
                    ->label('FCY Amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('remaining_amount_lcy')
                    ->label('LCY Amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('unrealized_gain_loss')
                    ->label('Unrealized G/L')
                    ->numeric(decimalPlaces: 2)
                    ->weight('bold')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->alignment('right'),

                IconColumn::make('adjusted')
                    ->label('Adj.')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('buffer_type')
                    ->options([
                        'receivable' => 'Receivables',
                        'payable' => 'Payables',
                        'bank' => 'Bank Accounts',
                    ]),
                TernaryFilter::make('adjusted')
                    ->label('Adjustment Status'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
