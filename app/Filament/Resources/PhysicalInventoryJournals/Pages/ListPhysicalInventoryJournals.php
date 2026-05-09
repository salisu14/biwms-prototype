<?php

namespace App\Filament\Resources\PhysicalInventoryJournals\Pages;

use App\Filament\Resources\PhysicalInventoryJournals\PhysicalInventoryJournalResource;
use App\Jobs\PopulatePhysicalInventoryLines;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListPhysicalInventoryJournals extends ListRecords
{
    protected static string $resource = PhysicalInventoryJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('calculate_inventory')
                ->label('Calculate Inventory')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->schema([
                    Select::make('location_code')
                        ->relationship('location', 'code')
                        ->required(),
                    Select::make('items_filter')
                        ->options([
                            'all' => 'All Items',
                            'with_stock' => 'Items with Stock',
                            'counting_period' => 'Counting Period Due',
                        ])
                        ->default('all'),
                ])
                ->action(function (array $data) {
                    // Dispatch job to populate journal lines from current inventory
                    PopulatePhysicalInventoryLines::dispatch($data);
                }),
        ];
    }
}
