<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories\Tables;

use App\Models\OpeningInventory;
use App\Services\Inventory\OpeningInventoryService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OpeningInventoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Document No.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('business.name')
                    ->placeholder('Global')
                    ->toggleable(),

                TextColumn::make('source')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        OpeningInventory::STATUS_DRAFT => 'warning',
                        OpeningInventory::STATUS_POSTED => 'success',
                        OpeningInventory::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines')
                    ->alignEnd(),

                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->getStateUsing(fn (OpeningInventory $record): string => (string) $record->lines()->sum('quantity_base'))
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('total_value')
                    ->label('Total Value')
                    ->getStateUsing(fn (OpeningInventory $record): string => (string) $record->lines()->sum('amount'))
                    ->money('NGN')
                    ->alignEnd(),

                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->placeholder('System')
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('postedBy.name')
                    ->label('Posted By')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('posted_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        OpeningInventory::STATUS_DRAFT => 'Draft',
                        OpeningInventory::STATUS_POSTED => 'Posted',
                        OpeningInventory::STATUS_CANCELLED => 'Cancelled',
                    ]),

                SelectFilter::make('source')
                    ->options([
                        'MANUAL' => 'Manual Entry',
                        'IMPORT' => 'Import',
                        'REPAIR_OPENING_STOCK' => 'Controlled Repair',
                        'SEED_OPENING_STOCK' => 'Seed Opening Stock',
                    ]),

                SelectFilter::make('business_id')
                    ->label('Business')
                    ->relationship('business', 'name'),

                Filter::make('posting_date')
                    ->form([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('posting_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('posting_date', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('update', $record) === true),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('post')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('post', $record) === true)
                        ->action(function (OpeningInventory $record): void {
                            app(OpeningInventoryService::class)->post($record, auth()->id());
                            Notification::make()->title('Opening inventory posted')->success()->send();
                        })
                ),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('cancel', $record) === true)
                        ->action(function (OpeningInventory $record): void {
                            app(OpeningInventoryService::class)->cancelDraft($record, auth()->id());
                            Notification::make()->title('Opening inventory cancelled')->success()->send();
                        })
                ),
                DeleteAction::make()
                    ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('delete', $record) === true),
            ])
            ->defaultSort('posting_date', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
