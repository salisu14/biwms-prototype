<?php

namespace App\Filament\Resources\OverheadCostCategories\RelationManagers;

use App\Filament\Resources\ActualOverheadCosts\ActualOverheadCostResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActualOverheadCostsRelationManager extends RelationManager
{
    protected static string $relationship = 'actualOverheadCosts';

    protected static ?string $relatedResource = ActualOverheadCostResource::class;

    protected static ?string $title = 'Actual Overhead Costs';

    protected static ?string $recordTitleAttribute = 'description';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_no')
                    ->label('Doc No.')
                    ->state(fn ($record): string => (string) ($record->document_no ?: $record->document_type ?: $record->description ?: 'N/A'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('period')
                    ->date('M Y')
                    ->sortable(),

                TextColumn::make('workCenter.name')
                    ->label('Work Center'),

                TextColumn::make('amount')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('allocated_amount')
                    ->label('Allocated')
                    ->money('NGN'),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'unallocated',
                        'warning' => 'partial',
                        'success' => 'fully_allocated',
                        'danger' => 'variance_posted',
                    ]),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
