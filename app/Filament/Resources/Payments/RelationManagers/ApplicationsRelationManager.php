<?php

namespace App\Filament\Resources\Payments\RelationManagers;

use App\Filament\Resources\Payments\PaymentResource;
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

class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    protected static ?string $relatedResource = PaymentResource::class;

    protected static ?string $title = 'Applied Documents';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('document_type')
                        ->disabled(),
                    TextInput::make('document_number')
                        ->label('Doc No.')
                        ->disabled(),
                    TextInput::make('amount_applied')
                        ->numeric()
                        ->disabled(),
                ]),
                Section::make('Application Impact')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('document_remaining_before')
                                ->label('Balance Before')
                                ->numeric()
                                ->disabled(),
                            TextInput::make('document_remaining_after')
                                ->label('Balance After')
                                ->numeric()
                                ->disabled(),
                        ]),
                    ]),
                Toggle::make('reversed')
                    ->label('Entry Reversed')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_number')
            ->columns([
                TextColumn::make('applied_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Applied Date'),

                TextColumn::make('document_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state))
                    ->color(fn (string $state): string => match ($state) {
                        'SALES_INVOICE', 'PURCHASE_INVOICE' => 'gray',
                        'SALES_CREDIT_MEMO', 'PURCHASE_CREDIT_MEMO' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('document_number')
                    ->label('Doc No.')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('amount_applied')
                    ->label('Applied')
                    ->money(fn ($record) => $record->payment?->currency_code)
                    ->alignment('right')
                    ->sortable(),

                TextColumn::make('discount_applied')
                    ->label('Disc.')
                    ->money(fn ($record) => $record->payment?->currency_code)
                    ->alignment('right')
                    ->color('success'),

                TextColumn::make('document_remaining_after')
                    ->label('Doc. Balance')
                    ->money(fn ($record) => $record->payment?->currency_code)
                    ->alignment('right')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                IconColumn::make('reversed')
                    ->boolean()
                    ->label('Rev.')
                    ->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('reversed'),
                SelectFilter::make('document_type')
                    ->options([
                        'SALES_INVOICE' => 'Sales Invoice',
                        'PURCHASE_INVOICE' => 'Purchase Invoice',
                        'SALES_CREDIT_MEMO' => 'Sales Credit Memo',
                    ]),
            ])
            ->headerActions([]) // Applications are usually created via a "Post Application" action on the Payment itself
            ->recordActions([
                ViewAction::make(),
                // Logic for "Unapply" could be added here as a custom action
            ])
            ->toolbarActions([]);
    }
}
