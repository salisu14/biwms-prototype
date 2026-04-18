<?php

namespace App\Filament\Resources\PriceChangeTemplates\Tables;

use App\Models\PriceChangeTemplate;
use App\Services\Inventory\ItemService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PriceChangeTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'warning',
                        'applied' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('adjustment_type')
                    ->label('Type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('value')
                    ->label('Adj. Value')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($record, $state) => $record->adjustment_type === 'percentage' ? $state.'%' : '₦'.number_format($state, 2)),

                TextColumn::make('base')
                    ->label('Base')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('effective_from')
                    ->label('Active From')
                    ->date()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'applied' => 'Applied',
                    ]),
            ])
            ->recordActions([
                Action::make('apply')
                    ->label('Apply Template')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PriceChangeTemplate $record): bool => $record->status === 'approved' || $record->status === 'draft')
                    ->action(function (PriceChangeTemplate $record, ItemService $service) {
                        try {
                            $service->applyPriceTemplate($record);

                            Notification::make()
                                ->title('Price Template Applied')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error Applying Template')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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
