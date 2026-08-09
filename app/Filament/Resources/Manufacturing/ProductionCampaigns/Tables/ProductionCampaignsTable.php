<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionCampaigns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductionCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('workCenter.name')->label('Work Center')->toggleable(),
                TextColumn::make('grouping_rule')->badge()->toggleable(),
                TextColumn::make('planned_start_at')->dateTime()->sortable(),
                TextColumn::make('planned_end_at')->dateTime()->sortable(),
                TextColumn::make('orders_count')->counts('orders')->label('Orders')->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'suggested' => 'Suggested',
                        'approved' => 'Approved',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
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
