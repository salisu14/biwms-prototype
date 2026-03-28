<?php

namespace App\Filament\Resources\DocumentHeaders\Tables;

use App\Enums\DocumentType;
use App\Enums\DocumentStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DocumentHeadersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('doc_date', 'desc')
            ->columns([
                TextColumn::make('doc_no')
                    ->label('Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                BadgeColumn::make('doc_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state): string => DocumentType::tryFrom($state)?->label() ?? $state)
//                    ->icon(fn ($state): ?string => DocumentType::tryFrom($state)?->icon())
                    ->color(fn ($state): string => DocumentType::tryFrom($state)?->color() ?? 'gray'),

                TextColumn::make('doc_date')
                    ->label('Doc Date')
                    ->date()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state): string => DocumentStatus::tryFrom($state)?->label() ?? $state)
//                    ->icon(fn ($state): ?string => DocumentStatus::tryFrom($state)?->icon())
                    ->color(fn ($state): string => DocumentStatus::tryFrom($state)?->color() ?? 'gray'),

                TextColumn::make('ledgerEntries_count')
                    ->label('Lines')
                    ->counts('ledgerEntries')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_value')
                    ->label('Total Value')
                    ->money('USD')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('(SELECT SUM(quantity * unit_cost) FROM item_ledgers WHERE item_ledgers.doc_id = document_headers.id) ' . $direction);
                    })
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    // FIX: Use mapWithKeys
                    ->options(
                        collect(DocumentStatus::cases())
                            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                    ),

                SelectFilter::make('doc_type')
                    ->label('Type')
                    // FIX: Use mapWithKeys
                    ->options(
                        collect(DocumentType::cases())
                            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                    ),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('doc_date', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('doc_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
